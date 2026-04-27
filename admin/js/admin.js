/**
 * NBEN Form Builder — Admin JS
 */
(function ($) {
    'use strict';

    const cfg  = window.nbenAdmin || {};
    const ajax = cfg.ajaxUrl;
    const nonce = cfg.nonce;

    let currentFormId    = 0;
    let currentQuestionId = 0;
    let allQuestions     = [];
    let currentChoices   = [];
    let currentLogic     = [];

    // ── Init ──────────────────────────────────────────────────────────────────
    $(function () {
        const $list = $('#nben-questions-list');
        if (!$list.length) return;

        currentFormId = parseInt($list.data('form-id'), 10) || 0;
        if (currentFormId) loadQuestions(currentFormId);

        bindEvents();
    });

    // ── Load all questions for the form ───────────────────────────────────────
    function loadQuestions(formId) {
        $.post(ajax, { action: 'nben_get_questions', nonce, form_id: formId })
            .done(res => {
                if (!res.success) return;
                allQuestions = res.data.questions || [];
                renderQuestionList(allQuestions);
            });
    }

    function renderQuestionList(questions) {
        const $list = $('#nben-questions-list');
        $list.empty();

        if (!questions.length) {
            $list.html('<p class="nben-empty-list">No fields yet. Add one from the left panel.</p>');
            return;
        }

        const tpl = $('#nben-question-row-tpl').html();
        questions.forEach(q => {
            const hasLogic = q.logic && q.logic.length
                ? '<span class="nben-badge nben-badge--logic" title="Has conditional logic">⟛ Logic</span>' : '';
            const hasPopup = q.popup
                ? '<span class="nben-badge nben-badge--popup" title="Has popup">💬 Popup</span>' : '';

            const html = tpl
                .replace(/{{id}}/g,           q.id)
                .replace('{{field_type}}',    q.field_type)
                .replace('{{label_en}}',      escHtml(q.label_en))
                .replace('{{question_key}}',  escHtml(q.question_key))
                .replace('{{has_logic}}',     hasLogic)
                .replace('{{has_popup}}',     hasPopup);

            $list.append(html);
        });

        // Make sortable
        $list.sortable({
            handle: '.nben-drag-handle',
            update: function () {
                const order = $list.find('.nben-question-row').map((_, el) => $(el).data('id')).get();
                $.post(ajax, { action: 'nben_reorder_questions', nonce, order });
            },
        });
    }

    // ── Event bindings ────────────────────────────────────────────────────────
    function bindEvents() {

        // Add field from palette
        $(document).on('click', '.nben-palette-btn', function () {
            if (!currentFormId) { alert('Please select or save a form first.'); return; }
            openEditor(null, $(this).data('type'));
        });

        // Edit question
        $(document).on('click', '.nben-edit-q', function () {
            const id = $(this).closest('.nben-question-row').data('id');
            const q  = allQuestions.find(x => x.id == id);
            if (q) openEditor(q);
        });

        // Editor close
        $('#nben-editor-close').on('click', closeEditor);

        // Tabs
        $(document).on('click', '.nben-tab-btn', function () {
            const tab = $(this).data('tab');
            $('.nben-tab-btn').removeClass('is-active');
            $('.nben-tab-panel').removeClass('is-active');
            $(this).addClass('is-active');
            $(`.nben-tab-panel[data-panel="${tab}"]`).addClass('is-active');
        });

        // Save form
        $('#nben-save-form').on('click', function () {
            const $btn = $(this);
            $btn.prop('disabled', true).text('Saving…');
            $.post(ajax, {
                action:      'nben_save_form',
                nonce,
                form_id:     $btn.data('form-id'),
                title:       $('#nben-form-title').val(),
                description: '',
                status:      $('#nben-form-status').is(':checked') ? 1 : 0,
            })
            .done(res => {
                const ok = res.success;
                $('.nben-save-status').text(ok ? '✓ Saved' : '✗ Error').css('color', ok ? 'green' : 'red');
                setTimeout(() => $('.nben-save-status').text(''), 2500);
            })
            .always(() => $btn.prop('disabled', false).text('Save Form'));
        });

        // Save question
        $('#nben-save-question').on('click', saveQuestion);

        // Delete question
        $('#nben-delete-question').on('click', function () {
            if (!confirm(cfg.i18n.confirmDelete)) return;
            $.post(ajax, { action: 'nben_delete_question', nonce, id: currentQuestionId })
                .done(() => { closeEditor(); loadQuestions(currentFormId); });
        });

        // Add choice
        $('#nben-add-choice').on('click', addChoiceRow);

        // Save choice
        $(document).on('click', '.nben-save-choice', function () {
            const $row = $(this).closest('.nben-choice-row');
            saveChoice($row);
        });

        // Delete choice
        $(document).on('click', '.nben-delete-choice', function () {
            if (!confirm(cfg.i18n.confirmDelete)) return;
            const $row = $(this).closest('.nben-choice-row');
            const id   = $row.data('id');
            if (id) {
                $.post(ajax, { action: 'nben_delete_choice', nonce, id }).done(() => $row.remove());
            } else {
                $row.remove();
            }
        });

        // Add logic rule
        $('#nben-add-logic').on('click', addLogicRow);

        // Source question change → load choices for that question
        $(document).on('change', '.nben-logic-source-q', function () {
            const qId   = $(this).val();
            const $row  = $(this).closest('.nben-logic-row');
            const $choiceSel = $row.find('.nben-logic-source-c');
            $choiceSel.html('<option value="">— Choice —</option>');
            if (!qId) return;
            const srcQ = allQuestions.find(x => x.id == qId);
            if (!srcQ) return;
            (srcQ.choices || []).forEach(c => {
                $choiceSel.append(`<option value="${c.id}">${escHtml(c.label_en)}</option>`);
            });
        });

        // Save logic rule
        $(document).on('click', '.nben-save-logic', function () {
            const $row = $(this).closest('.nben-logic-row');
            saveLogicRow($row);
        });

        // Delete logic rule
        $(document).on('click', '.nben-delete-logic', function () {
            if (!confirm(cfg.i18n.confirmDelete)) return;
            const $row = $(this).closest('.nben-logic-row');
            const id   = $row.find('.nben-logic-id').val();
            if (id) {
                $.post(ajax, { action: 'nben_delete_logic', nonce, id }).done(() => $row.remove());
            } else {
                $row.remove();
            }
        });

        // Save popup
        $(document).on('input', '#nben-popup-title-en, #nben-popup-title-fr, #nben-popup-content-en, #nben-popup-content-fr', debounce(savePopup, 1200));
    }

    // ── Open editor panel ─────────────────────────────────────────────────────
    function openEditor(q, defaultType) {
        currentQuestionId = q ? q.id : 0;
        const isNew = !q;

        $('#nben-editor-title').text(isNew ? 'New Field' : 'Edit Field');
        $('#nben-q-id').val(q ? q.id : '');
        $('#nben-q-form-id').val(currentFormId);
        $('#nben-q-key').val(q ? q.question_key : '');
        $('#nben-q-type').val(q ? q.field_type : (defaultType || 'radio'));
        $('#nben-q-label-en').val(q ? q.label_en : '');
        $('#nben-q-label-fr').val(q ? q.label_fr : '');
        $('#nben-q-help-en').val(q ? q.help_text_en : '');
        $('#nben-q-help-fr').val(q ? q.help_text_fr : '');
        $('#nben-q-required').prop('checked', q ? !!parseInt(q.required) : true);

        // Popup
        const popup = q && q.popup ? q.popup : {};
        $('#nben-popup-id').val(popup.id || '');
        $('#nben-popup-title-en').val(popup.title_en || '');
        $('#nben-popup-title-fr').val(popup.title_fr || '');
        $('#nben-popup-content-en').val(popup.content_en || '');
        $('#nben-popup-content-fr').val(popup.content_fr || '');

        // Choices
        renderChoiceEditor(q ? q.choices : []);

        // Logic
        renderLogicEditor(q ? q.logic : []);

        // Show/hide delete
        $('#nben-delete-question').toggle(!isNew);

        // Activate content tab
        $('.nben-tab-btn[data-tab=content]').trigger('click');

        $('#nben-question-editor').show();
    }

    function closeEditor() {
        $('#nben-question-editor').hide();
        currentQuestionId = 0;
    }

    // ── Save question ─────────────────────────────────────────────────────────
    function saveQuestion() {
        const id     = $('#nben-q-id').val();
        const formId = $('#nben-q-form-id').val();

        $.post(ajax, {
            action:       'nben_save_question',
            nonce,
            id,
            form_id:      formId,
            question_key: $('#nben-q-key').val(),
            field_type:   $('#nben-q-type').val(),
            label_en:     $('#nben-q-label-en').val(),
            label_fr:     $('#nben-q-label-fr').val(),
            help_text_en: $('#nben-q-help-en').val(),
            help_text_fr: $('#nben-q-help-fr').val(),
            required:     $('#nben-q-required').is(':checked') ? 1 : 0,
        })
        .done(res => {
            if (!res.success) { alert('Error saving field.'); return; }
            currentQuestionId = res.data.question_id;
            $('#nben-q-id').val(currentQuestionId);
            $('#nben-delete-question').show();

            // Save popup too
            savePopup();

            loadQuestions(currentFormId);
        });
    }

    // ── Choices editor ────────────────────────────────────────────────────────
    function renderChoiceEditor(choices) {
        const $list = $('#nben-choices-list').empty();
        (choices || []).forEach(c => $list.append(buildChoiceRow(c)));

        $list.sortable({
            handle: '.nben-drag-handle',
            update: function () {
                let i = 10;
                $list.find('.nben-choice-row').each(function () {
                    const id = $(this).data('id');
                    if (id) $.post(ajax, { action: 'nben_save_choice', nonce, id, sort_order: i, question_id: currentQuestionId });
                    i += 10;
                });
            },
        });
    }

    function buildChoiceRow(c) {
        const tpl = $('#nben-choice-row-tpl').html();
        return $(tpl
            .replace(/{{id}}/g,         c.id || '')
            .replace('{{choice_key}}',  c.choice_key || '')
            .replace('{{label_en}}',    escHtml(c.label_en || ''))
            .replace('{{label_fr}}',    escHtml(c.label_fr || ''))
            .replace('{{image_url}}',   escHtml(c.image_url || ''))
        ).attr('data-id', c.id || '');
    }

    function addChoiceRow() {
        $('#nben-choices-list').append(buildChoiceRow({}));
    }

    function saveChoice($row) {
        const id = $row.data('id');
        $.post(ajax, {
            action:      'nben_save_choice',
            nonce,
            id:          id || '',
            question_id: currentQuestionId,
            choice_key:  $row.find('.nben-choice-key').val(),
            label_en:    $row.find('.nben-choice-label-en').val(),
            label_fr:    $row.find('.nben-choice-label-fr').val(),
            image_url:   $row.find('.nben-choice-img').val(),
        })
        .done(res => {
            if (!res.success) return;
            $row.data('id', res.data.choice_id).attr('data-id', res.data.choice_id);
            flashSaved($row.find('.nben-save-choice'));
            loadQuestions(currentFormId); // refresh to pick up choice in logic dropdowns
        });
    }

    // ── Logic editor ──────────────────────────────────────────────────────────
    function renderLogicEditor(rules) {
        const $list = $('#nben-logic-list').empty();
        (rules || []).forEach(r => $list.append(buildLogicRow(r)));
    }

    function buildLogicRow(r) {
        // Build question options
        let qOpts = '';
        allQuestions.forEach(q => {
            if (q.id == currentQuestionId) return;
            qOpts += `<option value="${q.id}" ${r.source_question == q.id ? 'selected' : ''}>${escHtml(q.label_en)}</option>`;
        });

        const tpl  = $('#nben-logic-row-tpl').html();
        const $row = $(tpl
            .replace(/{{id}}/g,              r.id || '')
            .replace('{{questions_options}}', qOpts)
        );
        $row.attr('data-id', r.id || '');
        $row.find('.nben-logic-id').val(r.id || '');

        // Pre-fill source choices if editing existing rule
        if (r.source_question) {
            const srcQ = allQuestions.find(x => x.id == r.source_question);
            if (srcQ) {
                (srcQ.choices || []).forEach(c => {
                    $row.find('.nben-logic-source-c').append(
                        `<option value="${c.id}" ${r.source_choice == c.id ? 'selected' : ''}>${escHtml(c.label_en)}</option>`
                    );
                });
            }
        }
        if (r.action) $row.find('.nben-logic-action').val(r.action);
        return $row;
    }

    function addLogicRow() {
        $('#nben-logic-list').append(buildLogicRow({}));
    }

    function saveLogicRow($row) {
        $.post(ajax, {
            action:          'nben_save_logic',
            nonce,
            id:              $row.find('.nben-logic-id').val(),
            form_id:         currentFormId,
            target_id:       currentQuestionId,
            source_question: $row.find('.nben-logic-source-q').val(),
            source_choice:   $row.find('.nben-logic-source-c').val(),
            action:          $row.find('.nben-logic-action').val(),
        })
        .done(res => {
            if (!res.success) return;
            $row.find('.nben-logic-id').val(res.data.logic_id);
            $row.data('id', res.data.logic_id);
            flashSaved($row.find('.nben-save-logic'));
        });
    }

    // ── Popup save ────────────────────────────────────────────────────────────
    function savePopup() {
        if (!currentQuestionId) return;
        $.post(ajax, {
            action:      'nben_save_popup',
            nonce,
            id:          $('#nben-popup-id').val(),
            question_id: currentQuestionId,
            choice_id:   '',
            title_en:    $('#nben-popup-title-en').val(),
            title_fr:    $('#nben-popup-title-fr').val(),
            content_en:  $('#nben-popup-content-en').val(),
            content_fr:  $('#nben-popup-content-fr').val(),
        })
        .done(res => {
            if (res.success) $('#nben-popup-id').val(res.data.popup_id);
        });
    }

    // ── Utilities ─────────────────────────────────────────────────────────────
    function flashSaved($btn) {
        const orig = $btn.text();
        $btn.text('✓').prop('disabled', true);
        setTimeout(() => $btn.text(orig).prop('disabled', false), 1200);
    }

    function escHtml(str) {
        return $('<div>').text(str || '').html();
    }

    function debounce(fn, ms) {
        let timer;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), ms);
        };
    }

})(jQuery);
