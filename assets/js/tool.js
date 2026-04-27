/**
 * NBEN Cost Estimation Tool — Frontend Engine
 * Handles: step rendering, conditional logic, project fetching, cost estimation
 */
(function ($) {
    'use strict';

    const cfg  = window.nbenTool || {};
    const i18n = cfg.i18n || {};

    // ── State ─────────────────────────────────────────────────────────────────
    let formData      = null;   // { form, questions }
    let answers       = {};     // { question_key: choice_key | value }
    let answerIds     = {};     // { question_key: choice_id }
    let currentStep   = 0;
    let visibleSteps  = [];     // ordered array of question objects currently visible
    let selectedRef   = null;   // reference project chosen by user
    let selectedGrey  = null;

    // Special pseudo-steps appended after questions
    const STEP_PROJECT_SELECT  = '__project_select';
    const STEP_SIZE_COST       = '__size_cost';
    const STEP_BENEFITS        = '__benefits';
    const STEP_RESULTS         = '__results';

    // ── Init ──────────────────────────────────────────────────────────────────
    $(document).ready(function () {
        const $wrap = $('#nben-tool-wrap');
        if (!$wrap.length) return;

        loadFormData($wrap);

        $wrap.on('click', '.nben-btn--next',     () => navigate(1));
        $wrap.on('click', '.nben-btn--prev',     () => navigate(-1));
        $wrap.on('click', '.nben-modal-close, .nben-modal-overlay', function (e) {
            if ($(e.target).is('.nben-modal-overlay') || $(e.target).is('.nben-modal-close')) {
                closeModal();
            }
        });
        $(document).on('keydown', e => { if (e.key === 'Escape') closeModal(); });
    });

    // ── Load form definition ──────────────────────────────────────────────────
    function loadFormData($wrap) {
        $.post(cfg.ajaxUrl, {
            action: 'nben_get_form_data',
            nonce:  cfg.nonce,
        })
        .done(res => {
            if (!res.success) { showError(res.data.message); return; }
            formData = res.data;
            buildVisibleSteps();
            renderStep();
        })
        .fail(() => showError('Failed to load form.'));
    }

    // ── Conditional logic engine ───────────────────────────────────────────────
    function buildVisibleSteps() {
        if (!formData) return;
        visibleSteps = formData.questions.filter(q => isVisible(q));
    }

    function isVisible(q) {
        if (!q.logic || !q.logic.length) return true;

        // Group rules by operator
        const rules = q.logic;
        // Default: all rules with AND must pass; any rule with OR can unlock
        let andResults = [];
        let orResults  = [];

        rules.forEach(rule => {
            const sourceQ = formData.questions.find(x => x.id == rule.source_question);
            if (!sourceQ) return;
            const match = answerIds[sourceQ.question_key] == rule.source_choice
                       || answers[sourceQ.question_key]   == rule.source_choice;

            const result = rule.action === 'show' ? match : !match;
            if (rule.operator === 'OR') {
                orResults.push(result);
            } else {
                andResults.push(result);
            }
        });

        const andPass = andResults.length ? andResults.every(Boolean) : true;
        const orPass  = orResults.length  ? orResults.some(Boolean)   : true;
        return andPass && orPass;
    }

    // ── Navigation ────────────────────────────────────────────────────────────
    function navigate(dir) {
        if (dir === 1 && !validateCurrentStep()) return;

        // Rebuild visible steps after answer change
        buildVisibleSteps();

        // Determine full step list (questions + pseudo-steps)
        const allSteps = getAllSteps();
        const nextIdx  = currentStep + dir;

        if (nextIdx < 0) return;
        if (nextIdx >= allSteps.length) return;

        currentStep = nextIdx;
        renderStep();
    }

    function getAllSteps() {
        return [
            ...visibleSteps,
            { id: STEP_PROJECT_SELECT },
            { id: STEP_SIZE_COST },
            { id: STEP_BENEFITS },
            { id: STEP_RESULTS },
        ];
    }

    // ── Render current step ────────────────────────────────────────────────────
    function renderStep() {
        const allSteps = getAllSteps();
        const step     = allSteps[currentStep];
        const $steps   = $('.nben-steps');
        const lang     = $('#nben-tool-wrap').data('lang') || 'en';

        updateProgress(currentStep + 1, allSteps.length);

        $steps.addClass('nben-fade-out');
        setTimeout(() => {
            $steps.empty().removeClass('nben-fade-out').addClass('nben-fade-in');

            if (step.id === STEP_PROJECT_SELECT)  { renderProjectSelect($steps, lang); }
            else if (step.id === STEP_SIZE_COST)  { renderSizeCost($steps, lang); }
            else if (step.id === STEP_BENEFITS)   { renderBenefits($steps, lang); }
            else if (step.id === STEP_RESULTS)    { renderResults($steps, lang); }
            else                                   { renderQuestion($steps, step, lang); }

            updateNav(currentStep, allSteps.length);

            setTimeout(() => $steps.removeClass('nben-fade-in'), 300);
        }, 200);
    }

    // ── Render a question step ─────────────────────────────────────────────────
    function renderQuestion($container, q, lang) {
        const label = lang === 'fr' && q.label_fr ? q.label_fr : q.label_en;
        const help  = lang === 'fr' && q.help_text_fr ? q.help_text_fr : (q.help_text_en || '');

        const $step = $('<div>', { class: 'nben-step nben-step--question' });

        // Title
        const $title = $('<h2>', { class: 'nben-question-title', text: label });
        if (q.popup) {
            $title.append(popupTrigger(q.popup, lang));
        }
        $step.append($title);

        if (help) {
            $step.append($('<p>', { class: 'nben-question-help', html: help }));
        }

        // Choices
        const $choices = $('<div>', { class: `nben-choices nben-choices--${q.field_type}` });

        (q.choices || []).forEach(c => {
            const choiceLabel = lang === 'fr' && c.label_fr ? c.label_fr : c.label_en;
            const choiceDesc  = lang === 'fr' && c.description_fr ? c.description_fr : (c.description_en || '');
            const selected    = answerIds[q.question_key] == c.id;

            if (q.field_type === 'radio' || q.field_type === 'checkbox') {
                const $item = $('<label>', {
                    class: 'nben-choice-item' + (selected ? ' is-selected' : ''),
                    'data-question-key': q.question_key,
                    'data-choice-id':    c.id,
                    'data-choice-key':   c.choice_key,
                });

                $item.append($('<input>', {
                    type:    q.field_type,
                    name:    `nben_${q.question_key}`,
                    value:   c.choice_key,
                    checked: selected,
                }));

                const $info = $('<div>', { class: 'nben-choice-info' });

                if (c.image_url) {
                    $item.prepend($('<img>', { src: c.image_url, alt: choiceLabel, class: 'nben-choice-img', loading: 'lazy' }));
                }

                $info.append($('<span>', { class: 'nben-choice-label', text: choiceLabel }));

                if (choiceDesc) {
                    $info.append($('<p>', { class: 'nben-choice-desc', html: choiceDesc }));
                }

                if (c.popup) {
                    $info.append(popupTrigger(c.popup, lang));
                }

                $item.append($info);
                $choices.append($item);

            } else if (q.field_type === 'select') {
                // Handled below as <select>
            }
        });

        // Select field type
        if (q.field_type === 'select') {
            const $sel = $('<select>', { name: `nben_${q.question_key}`, class: 'nben-select' });
            $sel.append($('<option>', { value: '', text: '— ' + label + ' —' }));
            (q.choices || []).forEach(c => {
                const cl = lang === 'fr' && c.label_fr ? c.label_fr : c.label_en;
                $sel.append($('<option>', { value: c.choice_key, 'data-choice-id': c.id, text: cl, selected: answers[q.question_key] === c.choice_key }));
            });
            $choices.append($sel);
        }

        // Text / number / textarea
        if (['text', 'number', 'textarea'].includes(q.field_type)) {
            const tag = q.field_type === 'textarea' ? 'textarea' : 'input';
            const $inp = $(`<${tag}>`, {
                name:  `nben_${q.question_key}`,
                class: 'nben-input',
                type:  q.field_type === 'number' ? 'number' : 'text',
                value: answers[q.question_key] || '',
                placeholder: label,
            });
            if (q.field_type === 'textarea') $inp.text(answers[q.question_key] || '');
            $choices.append($inp);
        }

        $step.append($choices);
        $container.append($step);

        // Bind change events
        $step.on('change', 'input[type=radio], input[type=checkbox]', function () {
            const $label = $(this).closest('.nben-choice-item');
            const qKey   = $label.data('question-key');
            const cId    = $label.data('choice-id');
            const cKey   = $label.data('choice-key');

            if ($(this).is(':radio')) {
                $choices.find('.nben-choice-item').removeClass('is-selected');
                $label.addClass('is-selected');
                answers[qKey]   = cKey;
                answerIds[qKey] = cId;
            } else {
                $label.toggleClass('is-selected', this.checked);
                if (this.checked) {
                    answers[qKey]   = cKey;
                    answerIds[qKey] = cId;
                }
            }
            buildVisibleSteps();
        });

        $step.on('change', 'select.nben-select', function () {
            const qKey = q.question_key;
            answers[qKey]   = $(this).val();
            const $opt      = $(this).find(':selected');
            answerIds[qKey] = $opt.data('choice-id');
        });

        $step.on('input', 'input.nben-input, textarea.nben-input', function () {
            answers[q.question_key] = $(this).val();
        });
    }

    // ── Project selection step ─────────────────────────────────────────────────
    function renderProjectSelect($container, lang) {
        const projectType = answers['nbs_type'] || answers['project_type'] || '';
        const $step = $('<div>', { class: 'nben-step nben-step--projects' });
        $step.append($('<h2>', { text: i18n.selectProject || 'Select a Reference Project' }));

        if (!projectType) {
            $step.append($('<p>', { text: 'No project type selected.' }));
            $container.append($step);
            return;
        }

        // Limit toggle
        let limitMode = true;
        const $limitBtn = $('<button>', {
            type: 'button',
            class: 'nben-btn nben-btn--ghost nben-limit-btn',
            text: i18n.showAll || 'Show all options',
        });

        const $projectList = $('<div>', { class: 'nben-project-list' });
        $step.append($limitBtn, $('<div>', { class: 'nben-loading-projects', text: i18n.loading }), $projectList);
        $container.append($step);

        // Fetch projects
        $.post(cfg.ajaxUrl, {
            action:       'nben_fetch_projects',
            nonce:        cfg.nonce,
            project_type: projectType,
            infra_type:   'nbs',
        })
        .done(res => {
            $step.find('.nben-loading-projects').remove();
            if (!res.success || !res.data.projects.length) {
                $projectList.html(`<p class="nben-no-projects">${i18n.noProjects}</p>`);
                return;
            }

            const all     = res.data.projects;
            const limited = res.data.limited;

            function renderList(projects) {
                $projectList.empty();
                projects.forEach(p => {
                    const isSelected = selectedRef && selectedRef.id === p.id;
                    const desc = lang === 'fr' && p.description_fr ? p.description_fr : p.description;
                    const $card = $(`
                        <label class="nben-project-card ${isSelected ? 'is-selected' : ''}">
                            <input type="radio" name="nben_ref_project" value="${p.id}" ${isSelected ? 'checked' : ''}>
                            <div class="nben-project-card__inner">
                                ${p.thumbnail ? `<img src="${p.thumbnail}" alt="${escHtml(p.title)}" class="nben-project-card__img" loading="lazy">` : ''}
                                <div class="nben-project-card__body">
                                    <strong>${escHtml(p.title)}</strong>
                                    <span class="nben-project-location">${escHtml(p.location)} ${escHtml(p.province)}</span>
                                    <p>${escHtml(desc)}</p>
                                    <div class="nben-project-meta">
                                        <span>${p.total_size} ${p.size_unit}</span>
                                        <span>$${numFmt(p.cost_per_unit)} / ${p.size_unit} (CAD ${p.currency_year})</span>
                                    </div>
                                </div>
                            </div>
                        </label>
                    `);
                    $card.on('change', 'input[type=radio]', () => {
                        selectedRef = p;
                        $projectList.find('.nben-project-card').removeClass('is-selected');
                        $card.addClass('is-selected');
                    });
                    $projectList.append($card);
                });
            }

            renderList(limitMode ? limited : all);

            $limitBtn.on('click', function () {
                limitMode = !limitMode;
                $(this).text(limitMode ? (i18n.showAll || 'Show all') : (i18n.limitOptions || 'Limit options'));
                renderList(limitMode ? limited : all);
            });

            if (all.length <= 3) $limitBtn.hide();
        });
    }

    // ── Size & Cost step ───────────────────────────────────────────────────────
    function renderSizeCost($container, lang) {
        const $step = $('<div>', { class: 'nben-step nben-step--cost' });

        if (selectedRef) {
            $step.append($('<h2>', { text: '8. ' + (i18n.enterSize || 'Size and Cost Estimation') }));
            $step.append($(`
                <div class="nben-ref-summary">
                    <strong>${escHtml(selectedRef.title)}</strong> — ${escHtml(selectedRef.location)} ${escHtml(selectedRef.province)}<br>
                    ${selectedRef.total_size} ${selectedRef.size_unit} · $${numFmt(selectedRef.cost_per_unit)} / ${selectedRef.size_unit}
                </div>
            `));
        }

        $step.append($(`
            <div class="nben-size-input-group">
                <label class="nben-label">${i18n.enterSize || 'Project Size'}</label>
                <div class="nben-input-row">
                    <input id="nben-user-size" type="number" min="0.0001" step="0.0001" class="nben-input nben-input--size" placeholder="0.00">
                    <select id="nben-unit-select" class="nben-select nben-select--unit">
                        <option value="ha">${i18n.ha || 'Hectares (ha)'}</option>
                        <option value="m">${i18n.m || 'Linear Metres (m)'}</option>
                        <option value="m2">${i18n.m2 || 'Square Metres (m²)'}</option>
                        <option value="unit">${i18n.unit || 'Units'}</option>
                    </select>
                </div>
                <button type="button" class="nben-btn nben-btn--accent nben-calc-btn">${i18n.estimateCost || 'Calculate Estimate'}</button>
            </div>
            <div class="nben-cost-result" style="display:none">
                <div class="nben-cost-amount"></div>
            </div>
        `));

        $step.on('click', '.nben-calc-btn', function () {
            const size = parseFloat($('#nben-user-size').val());
            const unit = $('#nben-unit-select').val();
            if (!size || size <= 0 || !selectedRef) return;

            $.post(cfg.ajaxUrl, {
                action:                'nben_estimate_cost',
                nonce:                 cfg.nonce,
                reference_project_id:  selectedRef.id,
                user_size:             size,
                user_unit:             unit,
            })
            .done(res => {
                if (!res.success) return;
                const d = res.data;
                answers._estimated_cost = d.estimated_cost;
                answers._user_size      = size;
                answers._user_unit      = unit;

                $step.find('.nben-cost-result').show();
                $step.find('.nben-cost-amount').html(`
                    <span class="nben-cost-label">${i18n.costLabel || 'Estimated Cost'}</span>
                    <span class="nben-cost-value">${d.formatted_cost}</span>
                `);
            });
        });

        $container.append($step);
    }

    // ── Benefits step ──────────────────────────────────────────────────────────
    function renderBenefits($container, lang) {
        const $step = $('<div>', { class: 'nben-step nben-step--benefits' });
        $step.append($('<h2>', { text: 'Benefits of Nature-based Solutions' }));
        $step.append($(`
            <div class="nben-benefits-info">
                <p>Nature-based solutions generate a range of ecosystem benefits beyond their primary function, supporting climate adaptation and community well-being.</p>
            </div>
            <div class="nben-question">
                <label class="nben-label">Is your project accessible or visible to the public?</label>
                <label class="nben-choice-item">
                    <input type="radio" name="nben_public_access" value="yes"> Yes, accessible to the public
                </label>
                <label class="nben-choice-item">
                    <input type="radio" name="nben_public_access" value="partial"> Partially, visible only
                </label>
                <label class="nben-choice-item">
                    <input type="radio" name="nben_public_access" value="no"> No, completely private
                </label>
            </div>
        `));
        $step.on('change', 'input[name=nben_public_access]', function () {
            answers._public_access = $(this).val();
        });
        $container.append($step);
    }

    // ── Results step ───────────────────────────────────────────────────────────
    function renderResults($container, lang) {
        const $step = $('<div>', { class: 'nben-step nben-step--results' });

        const cost = answers._estimated_cost
            ? '$' + numFmt(answers._estimated_cost) + ' CAD'
            : '—';

        $step.append($(`
            <h2>Your Project Results</h2>
            <div class="nben-results-card">
                <h3>Project Summary</h3>
                <table class="nben-results-table">
                    <tr><th>Project Name</th><td>${escHtml(answers.project_name || '—')}</td></tr>
                    <tr><th>Project Size</th><td>${answers._user_size || '—'} ${answers._user_unit || ''}</td></tr>
                    <tr><th>NbS Type</th><td>${escHtml(answers.nbs_type || '—')}</td></tr>
                </table>

                <h3>Estimated Cost</h3>
                <div class="nben-result-cost">${cost}</div>
                <p class="nben-disclaimer">Based on reference project costs. Actual costs may vary. This estimate is for informational purposes only.</p>

                ${selectedRef ? `
                <h3>Reference Project</h3>
                <div class="nben-ref-card">
                    <strong>${escHtml(selectedRef.title)}</strong><br>
                    ${escHtml(selectedRef.location)} ${escHtml(selectedRef.province)}<br>
                    Size: ${selectedRef.total_size} ${selectedRef.size_unit} · Cost: $${numFmt(selectedRef.cost_per_unit)}/${selectedRef.size_unit}
                </div>` : ''}

                <div class="nben-results-actions">
                    <button class="nben-btn nben-btn--primary nben-restart-btn" type="button">Start Over</button>
                </div>
            </div>
        `));

        $step.on('click', '.nben-restart-btn', () => {
            answers     = {};
            answerIds   = {};
            selectedRef = null;
            currentStep = 0;
            buildVisibleSteps();
            renderStep();
        });

        $container.append($step);
    }

    // ── Validation ─────────────────────────────────────────────────────────────
    function validateCurrentStep() {
        const allSteps = getAllSteps();
        const step = allSteps[currentStep];
        if (!step || step.id === STEP_PROJECT_SELECT ||
            step.id === STEP_SIZE_COST || step.id === STEP_BENEFITS || step.id === STEP_RESULTS) return true;

        if (step.required && !answers[step.question_key]) {
            showValidationError(i18n.required || 'This field is required.');
            return false;
        }
        return true;
    }

    function showValidationError(msg) {
        let $err = $('.nben-validation-error');
        if (!$err.length) {
            $err = $('<div>', { class: 'nben-validation-error', role: 'alert' });
            $('.nben-nav').before($err);
        }
        $err.text(msg).show();
        setTimeout(() => $err.fadeOut(), 3000);
    }

    // ── Progress bar ───────────────────────────────────────────────────────────
    function updateProgress(current, total) {
        const pct = Math.round((current / total) * 100);
        $('.nben-progress-fill').css('width', pct + '%').attr('aria-valuenow', pct);
        const label = (i18n.stepOf || 'Step %1$s of %2$s').replace('%1$s', current).replace('%2$s', total);
        $('.nben-step-counter').text(label);
    }

    // ── Nav button visibility ─────────────────────────────────────────────────
    function updateNav(idx, total) {
        $('.nben-btn--prev').toggle(idx > 0);
        const isLast = idx === total - 1;
        $('.nben-btn--next').text(isLast ? (i18n.submit || 'See Results') + ' →' : (i18n.next || 'Next') + ' →');
    }

    // ── Popup trigger ─────────────────────────────────────────────────────────
    function popupTrigger(popup, lang) {
        const $btn = $('<button>', {
            type:  'button',
            class: 'nben-popup-trigger',
            'aria-label': i18n.learnMore || 'Learn more',
            html:  '<svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5" fill="none"/><text x="10" y="15" text-anchor="middle" font-size="12" font-weight="bold">i</text></svg>',
        });
        $btn.on('click', e => {
            e.preventDefault();
            const title   = lang === 'fr' && popup.title_fr   ? popup.title_fr   : (popup.title_en || '');
            const content = lang === 'fr' && popup.content_fr ? popup.content_fr : (popup.content_en || '');
            openModal(title, content);
        });
        return $btn;
    }

    // ── Modal ─────────────────────────────────────────────────────────────────
    function openModal(title, content) {
        const $body = $('.nben-modal-body');
        $body.html('');
        if (title)   $body.append($('<h3>', { text: title }));
        if (content) $body.append($('<div>', { html: content }));
        $('.nben-modal-overlay').show();
        $('.nben-modal').attr('tabindex', '-1').focus();
    }

    function closeModal() {
        $('.nben-modal-overlay').hide();
    }

    // ── Utils ─────────────────────────────────────────────────────────────────
    function escHtml(str) {
        return $('<div>').text(str || '').html();
    }

    function numFmt(n) {
        return Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function showError(msg) {
        $('.nben-steps').html(`<div class="nben-error">${escHtml(msg)}</div>`);
    }

})(jQuery);
