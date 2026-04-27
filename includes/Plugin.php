<?php
namespace NBEN;

defined( 'ABSPATH' ) || exit;

/**
 * Core plugin singleton.
 */
class Plugin {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function init(): void {
        // Register CPT + Taxonomies
        ( new CPT\Projects() )->register();

        // Admin
        if ( is_admin() ) {
            ( new Admin\FormBuilder() )->register();
            ( new Admin\ProjectMeta() )->register();
            ( new Admin\Settings() )->register();
        }

        // Frontend shortcode + assets
        ( new Frontend\Shortcode() )->register();
        ( new Frontend\Assets() )->register();

        // AJAX (both logged-in and guest)
        ( new Ajax\Handler() )->register();
    }
}
