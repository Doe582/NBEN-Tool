<?php defined('ABSPATH') || exit;
global $wpdb; ?>
<div class="wrap">
<h1><?php esc_html_e('NBEN Cost Estimation Tool', 'nben-tool'); ?></h1>

<div class="nben-dashboard-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-top:20px">

    <div class="nben-dash-card" style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:20px">
        <h3 style="margin-top:0;color:#5B3D8A">📋 Forms</h3>
        <?php
        $forms = $wpdb->get_results("SELECT id, title, status FROM {$wpdb->prefix}nben_forms");
echo '<p>'.count($forms).' form(s) configured</p>';
?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=nben-form-builder')); ?>" class="button button-primary">Open Form Builder</a>
    </div>

    <div class="nben-dash-card" style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:20px">
        <h3 style="margin-top:0;color:#5B3D8A">🌿 Projects</h3>
        <?php
$count = wp_count_posts('nben_project');
echo '<p>'.(int) ($count->publish ?? 0).' published project(s)</p>';
?>
        <a href="<?php echo esc_url(admin_url('edit.php?post_type=nben_project')); ?>" class="button">View Projects</a>
        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=nben_project')); ?>" class="button button-primary" style="margin-left:6px">Add Project</a>
    </div>

    <div class="nben-dash-card" style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:20px">
        <h3 style="margin-top:0;color:#5B3D8A">⚙️ Shortcode</h3>
        <?php $opts = get_option('nben_settings', []);
$fid = $opts['active_form_id'] ?? ''; ?>
        <p>Paste on any page:</p>
        <code style="display:block;background:#f6f7f7;padding:8px;border-radius:4px">[nben_tool<?php echo $fid ? ' form_id="'.(int) $fid.'"' : ''; ?>]</code>
        <a href="<?php echo esc_url(admin_url('admin.php?page=nben-settings')); ?>" class="button" style="margin-top:10px">Settings</a>
    </div>

</div>

<div style="margin-top:20px;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:20px">
    <h3 style="margin-top:0"><?php esc_html_e('Quick Setup Guide', 'nben-tool'); ?></h3>
    <ol>
        <li><?php esc_html_e('Go to Form Builder → add your questions and conditional logic rules', 'nben-tool'); ?></li>
        <li><?php esc_html_e('Add NbS Projects (and Grey Infrastructure projects) via NbS Projects', 'nben-tool'); ?></li>
        <li><?php esc_html_e('Assign each project the correct NbS Type, Hazard, and Infrastructure Type taxonomy', 'nben-tool'); ?></li>
        <li><?php esc_html_e('Set the active form in Settings', 'nben-tool'); ?></li>
        <li><?php esc_html_e('Use [nben_tool] shortcode on any page', 'nben-tool'); ?></li>
    </ol>
</div>
</div>
