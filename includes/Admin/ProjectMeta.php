<?php

namespace NBEN\Admin;

defined('ABSPATH') || exit;

/**
 * Adds custom meta boxes to the nben_project CPT edit screen.
 */
class ProjectMeta
{
    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'add_boxes']);
        add_action('save_post_nben_project', [$this, 'save'], 10, 2);
        add_filter('manage_nben_project_posts_columns', [$this, 'admin_columns']);
        add_action('manage_nben_project_posts_custom_column', [$this, 'admin_column_content'], 10, 2);
    }

    public function add_boxes(): void
    {
        add_meta_box(
            'nben_project_details',
            __('Project Details', 'nben-tool'),
            [$this, 'render_details_box'],
            'nben_project', 'normal', 'high'
        );
        add_meta_box(
            'nben_project_cost',
            __('Cost & Size', 'nben-tool'),
            [$this, 'render_cost_box'],
            'nben_project', 'side', 'high'
        );
    }

    // ── Render: Details ──────────────────────────────────────────────────────
    public function render_details_box(\WP_Post $post): void
    {
        wp_nonce_field('nben_project_save', 'nben_project_nonce');
        $location = get_post_meta($post->ID, '_nben_location', true);
        $province = get_post_meta($post->ID, '_nben_province', true);
        $desc_fr = get_post_meta($post->ID, '_nben_description_fr', true);
        $source_url = get_post_meta($post->ID, '_nben_source_url', true);
        $source_name = get_post_meta($post->ID, '_nben_source_name', true);
        $year = get_post_meta($post->ID, '_nben_year', true);
        ?>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Location', 'nben-tool'); ?></th>
                <td><input type="text" name="nben_location" value="<?php echo esc_attr($location); ?>" class="regular-text" placeholder="e.g. Fredericton, NB"></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Province', 'nben-tool'); ?></th>
                <td>
                    <select name="nben_province">
                        <option value=""><?php esc_html_e('— Select —', 'nben-tool'); ?></option>
                        <?php
                        $provinces = ['NB' => 'New Brunswick', 'NS' => 'Nova Scotia', 'PE' => 'Prince Edward Island', 'NL' => 'Newfoundland and Labrador', 'QC' => 'Quebec', 'ON' => 'Ontario', 'Other' => 'Other'];
        foreach ($provinces as $code => $name) {
            printf('<option value="%s" %s>%s</option>', esc_attr($code), selected($province, $code, false), esc_html($name));
        }
        ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Year', 'nben-tool'); ?></th>
                <td><input type="number" name="nben_year" value="<?php echo esc_attr($year); ?>" class="small-text" min="1990" max="2099"></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Description (FR)', 'nben-tool'); ?></th>
                <td><textarea name="nben_description_fr" rows="4" class="large-text"><?php echo esc_textarea($desc_fr); ?></textarea>
                <p class="description"><?php esc_html_e('French description (English goes in the main post content area above)', 'nben-tool'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Source Name', 'nben-tool'); ?></th>
                <td><input type="text" name="nben_source_name" value="<?php echo esc_attr($source_name); ?>" class="regular-text" placeholder="e.g. DUC 2023"></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Source URL', 'nben-tool'); ?></th>
                <td><input type="url" name="nben_source_url" value="<?php echo esc_attr($source_url); ?>" class="large-text"></td>
            </tr>
        </table>
        <?php
    }

    // ── Render: Cost & Size ──────────────────────────────────────────────────
    public function render_cost_box(\WP_Post $post): void
    {
        $total_size = get_post_meta($post->ID, '_nben_total_size', true);
        $size_unit = get_post_meta($post->ID, '_nben_size_unit', true);
        $cost_total = get_post_meta($post->ID, '_nben_cost_total', true);
        $cost_unit = get_post_meta($post->ID, '_nben_cost_per_unit', true);
        $currency = get_post_meta($post->ID, '_nben_currency_year', true) ?: '2022';
        ?>
        <p>
            <label><strong><?php esc_html_e('Total Size', 'nben-tool'); ?></strong></label><br>
            <input type="number" name="nben_total_size" value="<?php echo esc_attr($total_size); ?>" step="0.0001" min="0" class="widefat">
        </p>
        <p>
            <label><strong><?php esc_html_e('Size Unit', 'nben-tool'); ?></strong></label><br>
            <select name="nben_size_unit" class="widefat">
                <option value="ha"  <?php selected($size_unit, 'ha'); ?>>ha (hectares)</option>
                <option value="m"   <?php selected($size_unit, 'm'); ?>>m (linear metres)</option>
                <option value="m2"  <?php selected($size_unit, 'm2'); ?>>m² (square metres)</option>
                <option value="unit"<?php selected($size_unit, 'unit'); ?>>unit (per tree / item)</option>
            </select>
        </p>
        <p>
            <label><strong><?php esc_html_e('Total Cost (CAD)', 'nben-tool'); ?></strong></label><br>
            <input type="number" name="nben_cost_total" value="<?php echo esc_attr($cost_total); ?>" step="0.01" min="0" class="widefat">
        </p>
        <p>
            <label><strong><?php esc_html_e('Cost per Unit (CAD)', 'nben-tool'); ?></strong></label><br>
            <input type="number" name="nben_cost_per_unit" value="<?php echo esc_attr($cost_unit); ?>" step="0.01" min="0" class="widefat">
            <span class="description"><?php esc_html_e('Auto-calculated if left blank.', 'nben-tool'); ?></span>
        </p>
        <p>
            <label><strong><?php esc_html_e('Currency Year', 'nben-tool'); ?></strong></label><br>
            <input type="number" name="nben_currency_year" value="<?php echo esc_attr($currency); ?>" min="2000" max="2099" class="widefat">
        </p>
        <?php
    }

    // ── Save ─────────────────────────────────────────────────────────────────
    public function save(int $post_id, \WP_Post $post): void
    {
        if (
            ! isset($_POST['nben_project_nonce']) ||
            ! wp_verify_nonce($_POST['nben_project_nonce'], 'nben_project_save') ||
            defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ||
            ! current_user_can('edit_post', $post_id)
        ) {
            return;
        }

        $text_fields = [
            'nben_location' => '_nben_location',
            'nben_province' => '_nben_province',
            'nben_description_fr' => '_nben_description_fr',
            'nben_source_url' => '_nben_source_url',
            'nben_source_name' => '_nben_source_name',
        ];
        foreach ($text_fields as $post_key => $meta_key) {
            $val = isset($_POST[$post_key]) ? sanitize_text_field(wp_unslash($_POST[$post_key])) : '';
            update_post_meta($post_id, $meta_key, $val);
        }

        $num_fields = [
            'nben_year' => '_nben_year',
            'nben_total_size' => '_nben_total_size',
            'nben_cost_total' => '_nben_cost_total',
            'nben_cost_per_unit' => '_nben_cost_per_unit',
            'nben_currency_year' => '_nben_currency_year',
        ];
        foreach ($num_fields as $post_key => $meta_key) {
            $val = isset($_POST[$post_key]) ? floatval($_POST[$post_key]) : '';
            update_post_meta($post_id, $meta_key, $val);
        }

        $unit = isset($_POST['nben_size_unit']) ? sanitize_key($_POST['nben_size_unit']) : 'ha';
        update_post_meta($post_id, '_nben_size_unit', $unit);

        // Auto-calculate cost per unit if blank
        $total_size = (float) get_post_meta($post_id, '_nben_total_size', true);
        $cost_total = (float) get_post_meta($post_id, '_nben_cost_total', true);
        if ($total_size > 0 && $cost_total > 0 && ! get_post_meta($post_id, '_nben_cost_per_unit', true)) {
            update_post_meta($post_id, '_nben_cost_per_unit', round($cost_total / $total_size, 2));
        }
    }

    // ── Admin list columns ────────────────────────────────────────────────────
    public function admin_columns(array $cols): array
    {
        $new = [];
        foreach ($cols as $k => $v) {
            $new[$k] = $v;
            if ($k === 'title') {
                $new['location'] = __('Location', 'nben-tool');
                $new['size'] = __('Size', 'nben-tool');
                $new['cost_per_unit'] = __('Cost/Unit', 'nben-tool');
            }
        }

        return $new;
    }

    public function admin_column_content(string $col, int $post_id): void
    {
        switch ($col) {
            case 'location':
                echo esc_html(get_post_meta($post_id, '_nben_location', true).' '.get_post_meta($post_id, '_nben_province', true));
                break;
            case 'size':
                $size = get_post_meta($post_id, '_nben_total_size', true);
                $unit = get_post_meta($post_id, '_nben_size_unit', true);
                echo esc_html($size ? "$size $unit" : '—');
                break;
            case 'cost_per_unit':
                $cpu = get_post_meta($post_id, '_nben_cost_per_unit', true);
                $unit = get_post_meta($post_id, '_nben_size_unit', true);
                echo esc_html($cpu ? '$'.number_format($cpu, 2)."/$unit" : '—');
                break;
        }
    }
}
