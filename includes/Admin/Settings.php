<?php
namespace NBEN\Admin;

defined( 'ABSPATH' ) || exit;

class Settings {

    public function register(): void {
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    public function admin_menu(): void {
        // Top-level menu
        add_menu_page(
            __( 'NBEN Tool', 'nben-tool' ),
            __( 'NBEN Tool', 'nben-tool' ),
            'manage_options',
            'nben-tool',
            [ $this, 'page_dashboard' ],
            'dashicons-admin-tools',
            30
        );

        add_submenu_page( 'nben-tool', __( 'Dashboard', 'nben-tool' ),    __( 'Dashboard', 'nben-tool' ),    'manage_options', 'nben-tool',             [ $this, 'page_dashboard' ] );
        add_submenu_page( 'nben-tool', __( 'Form Builder', 'nben-tool' ), __( 'Form Builder', 'nben-tool' ), 'manage_options', 'nben-form-builder',    [ $this, 'page_form_builder' ] );
        add_submenu_page( 'nben-tool', __( 'NbS Projects', 'nben-tool' ), __( 'NbS Projects', 'nben-tool' ), 'manage_options', 'edit.php?post_type=nben_project' );
        // add_submenu_page( 'nben-tool', __( 'Import Projects', 'nben-tool' ), __( 'Import Projects', 'nben-tool' ), 'manage_options', 'nben-import', [ $this, 'page_import' ] );
        add_submenu_page( 'nben-tool', __( 'Settings', 'nben-tool' ),     __( 'Settings', 'nben-tool' ),     'manage_options', 'nben-settings',         [ $this, 'page_settings' ] );
    }

    public function register_settings(): void {
        register_setting( 'nben_settings_group', 'nben_settings', [ 'sanitize_callback' => [ $this, 'sanitize_settings' ] ] );

        add_settings_section( 'nben_general', __( 'General Settings', 'nben-tool' ), null, 'nben-settings' );

        add_settings_field( 'active_form_id', __( 'Active Form ID', 'nben-tool' ), function () {
            global $wpdb;
            $forms = $wpdb->get_results( "SELECT id, title FROM {$wpdb->prefix}nben_forms WHERE status=1" );
            $current = (int) ( get_option( 'nben_settings' )['active_form_id'] ?? 0 );
            echo '<select name="nben_settings[active_form_id]">';
            foreach ( $forms as $f ) {
                printf( '<option value="%d" %s>%s</option>', $f->id, selected( $current, $f->id, false ), esc_html( $f->title ) );
            }
            echo '</select>';
        }, 'nben-settings', 'nben_general' );

        add_settings_field( 'primary_color', __( 'Primary Color', 'nben-tool' ), function () {
            $opts  = get_option( 'nben_settings', [] );
            $color = $opts['primary_color'] ?? '#5B3D8A';
            echo "<input type='color' name='nben_settings[primary_color]' value='" . esc_attr( $color ) . "'>";
        }, 'nben-settings', 'nben_general' );

        add_settings_field( 'accent_color', __( 'Accent Color', 'nben-tool' ), function () {
            $opts  = get_option( 'nben_settings', [] );
            $color = $opts['accent_color'] ?? '#2FAB7F';
            echo "<input type='color' name='nben_settings[accent_color]' value='" . esc_attr( $color ) . "'>";
        }, 'nben-settings', 'nben_general' );

        add_settings_field( 'default_lang', __( 'Default Language', 'nben-tool' ), function () {
            $opts = get_option( 'nben_settings', [] );
            $lang = $opts['default_lang'] ?? 'en';
            echo '<select name="nben_settings[default_lang]">
                    <option value="en" ' . selected( $lang, 'en', false ) . '>English</option>
                    <option value="fr" ' . selected( $lang, 'fr', false ) . '>Français</option>
                  </select>';
        }, 'nben-settings', 'nben_general' );
    }

    public function sanitize_settings( array $input ): array {
        return [
            'active_form_id' => absint( $input['active_form_id'] ?? 0 ),
            'primary_color'  => sanitize_hex_color( $input['primary_color'] ?? '#5B3D8A' ),
            'accent_color'   => sanitize_hex_color( $input['accent_color'] ?? '#2FAB7F' ),
            'default_lang'   => in_array( $input['default_lang'] ?? 'en', [ 'en', 'fr' ], true ) ? $input['default_lang'] : 'en',
        ];
    }

    public function enqueue( string $hook ): void {
        if ( strpos( $hook, 'nben' ) === false && $hook !== 'post.php' && $hook !== 'post-new.php' ) return;
        wp_enqueue_style( 'nben-admin', NBEN_PLUGIN_URL . 'admin/css/admin.css', [], NBEN_VERSION );
        wp_enqueue_script( 'nben-admin', NBEN_PLUGIN_URL . 'admin/js/admin.js', [ 'jquery', 'jquery-ui-sortable' ], NBEN_VERSION, true );
        wp_localize_script( 'nben-admin', 'nbenAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'nben_admin_nonce' ),
            'i18n'    => [
                'confirmDelete'   => __( 'Are you sure you want to delete this?', 'nben-tool' ),
                'addChoice'       => __( '+ Add Choice', 'nben-tool' ),
                'addLogicRule'    => __( '+ Add Rule', 'nben-tool' ),
                'saving'          => __( 'Saving…', 'nben-tool' ),
                'saved'           => __( 'Saved!', 'nben-tool' ),
                'error'           => __( 'Error — please try again.', 'nben-tool' ),
            ],
        ] );
    }

    // ── Pages ────────────────────────────────────────────────────────────────

    public function page_dashboard(): void {
        require NBEN_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    public function page_form_builder(): void {
        require NBEN_PLUGIN_DIR . 'admin/views/form-builder.php';
    }

    public function page_import(): void {
        require NBEN_PLUGIN_DIR . 'admin/views/import.php';
    }

    public function page_settings(): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'NBEN Tool Settings', 'nben-tool' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'nben_settings_group' ); ?>
                <?php do_settings_sections( 'nben-settings' ); ?>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
