<?php

namespace NBEN\Frontend;

defined('ABSPATH') || exit;

class Shortcode
{
    public function register(): void
    {
        add_shortcode('nben_tool', [$this, 'render']);
    }

    public function render(array $atts): string
    {
        $atts = shortcode_atts([
            'form_id' => '',
            'lang' => '',
        ], $atts, 'nben_tool');

        $opts = get_option('nben_settings', []);
        $form_id = $atts['form_id'] ? absint($atts['form_id']) : absint($opts['active_form_id'] ?? 0);
        $lang = $atts['lang'] ?: ($opts['default_lang'] ?? 'en');

        // WPML compat – detect current language
        if (defined('ICL_LANGUAGE_CODE')) {
            $lang = ICL_LANGUAGE_CODE === 'fr' ? 'fr' : 'en';
        }

        ob_start();
        include NBEN_PLUGIN_DIR.'templates/tool-wrapper.php';

        return ob_get_clean();
    }
}

class Assets
{
    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(): void
    {
        global $post;
        // Only load on pages that have the shortcode
        if (! is_a($post, 'WP_Post') || ! has_shortcode($post->post_content, 'nben_tool')) {
            return;
        }

        $opts = get_option('nben_settings', []);

        wp_enqueue_style('nben-tool', NBEN_PLUGIN_URL.'assets/css/tool.css', [], NBEN_VERSION);
        wp_enqueue_script('nben-tool', NBEN_PLUGIN_URL.'assets/js/tool.js', ['jquery'], NBEN_VERSION, true);

        wp_localize_script('nben-tool', 'nbenTool', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nben_frontend_nonce'),
            'primaryColor' => $opts['primary_color'] ?? '#5B3D8A',
            'accentColor' => $opts['accent_color'] ?? '#2FAB7F',
            'defaultLang' => $opts['default_lang'] ?? 'en',
            'pluginUrl' => NBEN_PLUGIN_URL,
            'i18n' => [
                'next' => __('Next', 'nben-tool'),
                'previous' => __('Previous', 'nben-tool'),
                'submit' => __('See Results', 'nben-tool'),
                'loading' => __('Loading…', 'nben-tool'),
                'selectProject' => __('Select a reference project', 'nben-tool'),
                'estimateCost' => __('Calculate Estimate', 'nben-tool'),
                'required' => __('This field is required.', 'nben-tool'),
                'limitOptions' => __('Limit to 3 options', 'nben-tool'),
                'showAll' => __('Show all options', 'nben-tool'),
                'noProjects' => __('No matching projects found.', 'nben-tool'),
                'costLabel' => __('Estimated Cost', 'nben-tool'),
                'perUnit' => __('Cost per unit', 'nben-tool'),
                'learnMore' => __('Learn more', 'nben-tool'),
                'close' => __('✕ Close', 'nben-tool'),
                'ha' => __('Hectares (ha)', 'nben-tool'),
                'm' => __('Linear Metres (m)', 'nben-tool'),
                'm2' => __('Square Metres (m²)', 'nben-tool'),
                'unit' => __('Units (per item)', 'nben-tool'),
                'stepOf' => __('Step %1$s of %2$s', 'nben-tool'),
                'enterSize' => __('Enter project size', 'nben-tool'),
            ],
        ]);
    }
}
