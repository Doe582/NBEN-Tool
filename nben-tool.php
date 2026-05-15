<?php

use NBEN\Plugin;

/**
 * Plugin Name: NBEN Cost Estimation Tool
 * Plugin URI:  https://nben.ca
 * Description: A dynamic multi-step cost estimation tool for Nature-based Solutions (NbS) with conditional logic form builder, project database, and bilingual (EN/FR) support.
 * Version:     1.0.0
 * Author:      NBEN / New Brunswick Environmental Network
 * Text Domain: nben-tool
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */
defined('ABSPATH') || exit;

// ─── Constants ────────────────────────────────────────────────────────────────
define('NBEN_VERSION', '1.0.0');
define('NBEN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NBEN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NBEN_PLUGIN_FILE', __FILE__);

// ─── Autoload ─────────────────────────────────────────────────────────────────
spl_autoload_register(function ($class) {
    $prefix = 'NBEN\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $file = NBEN_PLUGIN_DIR.'includes/'.$relative.'.php';
    if (file_exists($file)) {
        require $file;
    }
});

// ─── Boot ─────────────────────────────────────────────────────────────────────
add_action('plugins_loaded', function () {
    load_plugin_textdomain('nben-tool', false, dirname(plugin_basename(__FILE__)).'/languages');
    Plugin::instance()->init();
});

register_activation_hook(__FILE__, ['NBEN\Installer', 'activate']);
register_deactivation_hook(__FILE__, ['NBEN\Installer', 'deactivate']);

echo '    te4st.  ';
