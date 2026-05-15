<?php

namespace NBEN\Ajax;

use NBEN\Admin\FormBuilder;

defined('ABSPATH') || exit;

/**
 * Frontend AJAX handlers (available to non-logged-in users).
 */
class Handler
{
    public function register(): void
    {
        $actions = [
            'nben_get_form_data' => 'get_form_data',
            'nben_fetch_projects' => 'fetch_projects',
            'nben_estimate_cost' => 'estimate_cost',
        ];
        foreach ($actions as $action => $method) {
            add_action("wp_ajax_{$action}", [$this, $method]);
            add_action("wp_ajax_nopriv_{$action}", [$this, $method]);
        }
    }

    // ── Get form definition (questions + logic) ───────────────────────────────
    public function get_form_data(): void
    {
        check_ajax_referer('nben_frontend_nonce', 'nonce');

        $opts = get_option('nben_settings', []);
        $form_id = absint($opts['active_form_id'] ?? 0);

        if (! $form_id) {
            wp_send_json_error(['message' => __('No active form configured.', 'nben-tool')]);
        }

        global $wpdb;
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}nben_forms WHERE id=%d AND status=1",
            $form_id
        ), ARRAY_A);

        if (! $form) {
            wp_send_json_error(['message' => __('Form not found.', 'nben-tool')]);
        }

        $questions = FormBuilder::get_form_questions($form_id);
        wp_send_json_success(['form' => $form, 'questions' => $questions]);
    }

    // ── Fetch matching projects ───────────────────────────────────────────────
    public function fetch_projects(): void
    {
        check_ajax_referer('nben_frontend_nonce', 'nonce');

        $project_type_slug = sanitize_text_field(wp_unslash($_POST['project_type'] ?? ''));
        $infra_type = sanitize_text_field(wp_unslash($_POST['infra_type'] ?? 'nbs'));

        if (empty($project_type_slug)) {
            wp_send_json_error(['message' => __('Project type is required.', 'nben-tool')]);
        }

        $tax_query = [
            'relation' => 'AND',
            [
                'taxonomy' => 'nben_project_type',
                'field' => 'slug',
                'terms' => $project_type_slug,
            ],
        ];

        if ($infra_type) {
            $tax_query[] = [
                'taxonomy' => 'nben_infra_type',
                'field' => 'slug',
                'terms' => sanitize_key($infra_type),
            ];
        }

        $args = [
            'post_type' => 'nben_project',
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'tax_query' => $tax_query,
            'orderby' => 'meta_value_num',
            'meta_key' => '_nben_cost_per_unit',
            'order' => 'ASC',
        ];

        $query = new \WP_Query($args);
        $projects = [];

        foreach ($query->posts as $post) {
            $projects[] = [
                'id' => $post->ID,
                'title' => get_the_title($post),
                'description' => get_the_excerpt($post),
                'description_fr' => get_post_meta($post->ID, '_nben_description_fr', true),
                'location' => get_post_meta($post->ID, '_nben_location', true),
                'province' => get_post_meta($post->ID, '_nben_province', true),
                'total_size' => (float) get_post_meta($post->ID, '_nben_total_size', true),
                'size_unit' => get_post_meta($post->ID, '_nben_size_unit', true),
                'cost_total' => (float) get_post_meta($post->ID, '_nben_cost_total', true),
                'cost_per_unit' => (float) get_post_meta($post->ID, '_nben_cost_per_unit', true),
                'currency_year' => get_post_meta($post->ID, '_nben_currency_year', true) ?: '2022',
                'source_name' => get_post_meta($post->ID, '_nben_source_name', true),
                'source_url' => get_post_meta($post->ID, '_nben_source_url', true),
                'thumbnail' => get_the_post_thumbnail_url($post, 'medium') ?: '',
            ];
        }

        // "Limit options" feature: return low/mid/high representatives
        $limited = $this->get_low_mid_high($projects);

        wp_send_json_success([
            'projects' => $projects,
            'limited' => $limited,
            'total' => count($projects),
        ]);
    }

    // ── Cost estimation ───────────────────────────────────────────────────────
    public function estimate_cost(): void
    {
        check_ajax_referer('nben_frontend_nonce', 'nonce');

        $project_id = absint($_POST['reference_project_id'] ?? 0);
        $user_size = floatval($_POST['user_size'] ?? 0);
        $user_unit = sanitize_key(wp_unslash($_POST['user_unit'] ?? 'ha'));

        if (! $project_id || $user_size <= 0) {
            wp_send_json_error(['message' => __('Invalid input.', 'nben-tool')]);
        }

        $cpu = (float) get_post_meta($project_id, '_nben_cost_per_unit', true);
        $ref_unit = get_post_meta($project_id, '_nben_size_unit', true);
        $cy = get_post_meta($project_id, '_nben_currency_year', true) ?: '2022';

        if ($cpu <= 0) {
            wp_send_json_error(['message' => __('Reference project has no cost data.', 'nben-tool')]);
        }

        // Unit conversion if needed (user entered m2, reference is ha etc.)
        $converted_size = $this->convert_size($user_size, $user_unit, $ref_unit);
        $estimated_cost = round($converted_size * $cpu, 2);

        wp_send_json_success([
            'estimated_cost' => $estimated_cost,
            'formatted_cost' => '$'.number_format($estimated_cost, 0).' CAD '.$cy,
            'cost_per_unit' => $cpu,
            'ref_unit' => $ref_unit,
            'converted_size' => $converted_size,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function get_low_mid_high(array $projects): array
    {
        if (count($projects) <= 3) {
            return $projects;
        }

        $costs = array_column($projects, 'cost_per_unit');
        sort($costs);

        $low = $costs[0];
        $high = $costs[count($costs) - 1];
        $mid = $costs[(int) floor((count($costs) - 1) / 2)];

        $result = [];
        $picked = [];
        foreach ([$low, $mid, $high] as $target) {
            foreach ($projects as $p) {
                if (in_array($p['id'], $picked, true)) {
                    continue;
                }
                if ($p['cost_per_unit'] == $target) {
                    $result[] = $p;
                    $picked[] = $p['id'];
                    break;
                }
            }
        }

        return $result;
    }

    private function convert_size(float $size, string $from, string $to): float
    {
        if ($from === $to) {
            return $size;
        }

        // Convert everything to m2 first
        $to_m2 = [
            'ha' => 10000,
            'm2' => 1,
            'm' => 1,    // linear — keep as-is
            'unit' => 1,
        ];
        $m2 = $size * ($to_m2[$from] ?? 1);

        $from_m2 = [
            'ha' => 1 / 10000,
            'm2' => 1,
            'm' => 1,
            'unit' => 1,
        ];

        return $m2 * ($from_m2[$to] ?? 1);
    }
}
