<?php
defined('ABSPATH') || exit;
$primary = $opts['primary_color'] ?? '#5B3D8A';
$accent = $opts['accent_color'] ?? '#2FAB7F';
?>
<div id="nben-tool-wrap"
     data-form-id="<?php echo esc_attr($form_id); ?>"
     data-lang="<?php echo esc_attr($lang); ?>"
     style="--nben-primary:<?php echo esc_attr($primary); ?>;--nben-accent:<?php echo esc_attr($accent); ?>">

    <!-- Progress bar -->
    <div class="nben-progress-wrap" aria-hidden="true">
        <div class="nben-progress-bar">
            <div class="nben-progress-fill" style="width:0%"></div>
        </div>
        <span class="nben-step-counter"></span>
    </div>

    <!-- Step container – questions injected here by JS -->
    <div class="nben-steps" role="form" aria-live="polite">
        <div class="nben-loading">
            <span class="nben-spinner"></span>
            <?php esc_html_e('Loading…', 'nben-tool'); ?>
        </div>
    </div>

    <!-- Navigation -->
    <div class="nben-nav">
        <button class="nben-btn nben-btn--outline nben-btn--prev" type="button" style="display:none">
            ← <?php esc_html_e('Previous', 'nben-tool'); ?>
        </button>
        <button class="nben-btn nben-btn--primary nben-btn--next" type="button">
            <?php esc_html_e('Next', 'nben-tool'); ?> →
        </button>
    </div>

    <!-- Results panel (hidden until end) -->
    <div class="nben-results" style="display:none" aria-live="polite"></div>

    <!-- Modal / popup -->
    <div class="nben-modal-overlay" role="dialog" aria-modal="true" style="display:none">
        <div class="nben-modal">
            <button class="nben-modal-close" type="button" aria-label="<?php esc_attr_e('Close', 'nben-tool'); ?>">✕</button>
            <div class="nben-modal-body"></div>
        </div>
    </div>

</div>
