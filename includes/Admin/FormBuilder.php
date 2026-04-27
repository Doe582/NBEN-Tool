<?php
namespace NBEN\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * AJAX handlers for the Form Builder UI.
 * All operations use nben_admin_nonce.
 */
class FormBuilder {

    public function register(): void {
        // AJAX actions (admin only)
        $actions = [
            'nben_get_form'           => 'ajax_get_form',
            'nben_save_form'          => 'ajax_save_form',
            'nben_save_question'      => 'ajax_save_question',
            'nben_delete_question'    => 'ajax_delete_question',
            'nben_reorder_questions'  => 'ajax_reorder_questions',
            'nben_save_choice'        => 'ajax_save_choice',
            'nben_delete_choice'      => 'ajax_delete_choice',
            'nben_save_logic'         => 'ajax_save_logic',
            'nben_delete_logic'       => 'ajax_delete_logic',
            'nben_save_popup'         => 'ajax_save_popup',
            'nben_get_questions'      => 'ajax_get_questions',
        ];
        foreach ( $actions as $action => $method ) {
            add_action( "wp_ajax_{$action}", [ $this, $method ] );
        }
    }

    // ── Security helper ───────────────────────────────────────────────────────
    private function check_nonce(): void {
        if ( ! check_ajax_referer( 'nben_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed', 'nben-tool' ) ], 403 );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Insufficient permissions', 'nben-tool' ) ], 403 );
        }
    }

    // ── Form CRUD ─────────────────────────────────────────────────────────────
    public function ajax_get_form(): void {
        $this->check_nonce();
        global $wpdb;

        $form_id = absint( $_POST['form_id'] ?? 0 );
        $form    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}nben_forms WHERE id=%d", $form_id ) );
        if ( ! $form ) {
            wp_send_json_error( [ 'message' => 'Form not found' ], 404 );
        }

        $questions = $this->get_form_questions( $form_id );
        wp_send_json_success( [ 'form' => $form, 'questions' => $questions ] );
    }

    public function ajax_save_form(): void {
        $this->check_nonce();
        global $wpdb;

        $form_id = absint( $_POST['form_id'] ?? 0 );
        $data    = [
            'title'       => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
            'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
            'status'      => absint( $_POST['status'] ?? 1 ),
        ];

        if ( $form_id ) {
            $wpdb->update( "{$wpdb->prefix}nben_forms", $data, [ 'id' => $form_id ] );
        } else {
            $slug = sanitize_title( $data['title'] );
            $data['slug'] = $slug;
            $wpdb->insert( "{$wpdb->prefix}nben_forms", $data );
            $form_id = $wpdb->insert_id;
        }
        wp_send_json_success( [ 'form_id' => $form_id ] );
    }

    // ── Question CRUD ─────────────────────────────────────────────────────────
    public function ajax_save_question(): void {
        $this->check_nonce();
        global $wpdb;

        $id      = absint( $_POST['id'] ?? 0 );
        $form_id = absint( $_POST['form_id'] ?? 0 );

        $data = [
            'form_id'      => $form_id,
            'parent_id'    => $_POST['parent_id'] ? absint( $_POST['parent_id'] ) : null,
            'question_key' => sanitize_key( wp_unslash( $_POST['question_key'] ?? '' ) ),
            'label_en'     => sanitize_textarea_field( wp_unslash( $_POST['label_en'] ?? '' ) ),
            'label_fr'     => sanitize_textarea_field( wp_unslash( $_POST['label_fr'] ?? '' ) ),
            'help_text_en' => sanitize_textarea_field( wp_unslash( $_POST['help_text_en'] ?? '' ) ),
            'help_text_fr' => sanitize_textarea_field( wp_unslash( $_POST['help_text_fr'] ?? '' ) ),
            'field_type'   => sanitize_key( wp_unslash( $_POST['field_type'] ?? 'radio' ) ),
            'required'     => absint( $_POST['required'] ?? 1 ),
            'sort_order'   => absint( $_POST['sort_order'] ?? 0 ),
            'settings'     => wp_json_encode( (array) ( $_POST['settings'] ?? [] ) ),
        ];

        if ( $id ) {
            $wpdb->update( "{$wpdb->prefix}nben_questions", $data, [ 'id' => $id ] );
        } else {
            // Auto sort_order
            $max = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(sort_order) FROM {$wpdb->prefix}nben_questions WHERE form_id=%d", $form_id ) );
            $data['sort_order'] = $max + 10;
            $wpdb->insert( "{$wpdb->prefix}nben_questions", $data );
            $id = $wpdb->insert_id;
        }
        wp_send_json_success( [ 'question_id' => $id ] );
    }

    public function ajax_delete_question(): void {
        $this->check_nonce();
        global $wpdb;
        $id = absint( $_POST['id'] ?? 0 );
        $wpdb->delete( "{$wpdb->prefix}nben_questions", [ 'id' => $id ] );
        $wpdb->delete( "{$wpdb->prefix}nben_choices",   [ 'question_id' => $id ] );
        $wpdb->delete( "{$wpdb->prefix}nben_logic",     [ 'target_id' => $id ] );
        $wpdb->delete( "{$wpdb->prefix}nben_logic",     [ 'source_question' => $id ] );
        $wpdb->delete( "{$wpdb->prefix}nben_popups",    [ 'question_id' => $id ] );
        wp_send_json_success();
    }

    public function ajax_reorder_questions(): void {
        $this->check_nonce();
        global $wpdb;
        $order = array_map( 'absint', (array) ( $_POST['order'] ?? [] ) );
        foreach ( $order as $i => $question_id ) {
            $wpdb->update( "{$wpdb->prefix}nben_questions", [ 'sort_order' => ( $i + 1 ) * 10 ], [ 'id' => $question_id ] );
        }
        wp_send_json_success();
    }

    // ── Choice CRUD ───────────────────────────────────────────────────────────
    public function ajax_save_choice(): void {
        $this->check_nonce();
        global $wpdb;

        $id = absint( $_POST['id'] ?? 0 );
        $data = [
            'question_id'    => absint( $_POST['question_id'] ?? 0 ),
            'choice_key'     => sanitize_key( wp_unslash( $_POST['choice_key'] ?? '' ) ),
            'label_en'       => sanitize_text_field( wp_unslash( $_POST['label_en'] ?? '' ) ),
            'label_fr'       => sanitize_text_field( wp_unslash( $_POST['label_fr'] ?? '' ) ),
            'description_en' => sanitize_textarea_field( wp_unslash( $_POST['description_en'] ?? '' ) ),
            'description_fr' => sanitize_textarea_field( wp_unslash( $_POST['description_fr'] ?? '' ) ),
            'image_url'      => esc_url_raw( wp_unslash( $_POST['image_url'] ?? '' ) ),
            'sort_order'     => absint( $_POST['sort_order'] ?? 0 ),
        ];

        if ( $id ) {
            $wpdb->update( "{$wpdb->prefix}nben_choices", $data, [ 'id' => $id ] );
        } else {
            $max = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(sort_order) FROM {$wpdb->prefix}nben_choices WHERE question_id=%d", $data['question_id'] ) );
            $data['sort_order'] = $max + 10;
            $wpdb->insert( "{$wpdb->prefix}nben_choices", $data );
            $id = $wpdb->insert_id;
        }
        wp_send_json_success( [ 'choice_id' => $id ] );
    }

    public function ajax_delete_choice(): void {
        $this->check_nonce();
        global $wpdb;
        $id = absint( $_POST['id'] ?? 0 );
        $wpdb->delete( "{$wpdb->prefix}nben_choices", [ 'id' => $id ] );
        $wpdb->delete( "{$wpdb->prefix}nben_logic",   [ 'source_choice' => $id ] );
        $wpdb->delete( "{$wpdb->prefix}nben_popups",  [ 'choice_id' => $id ] );
        wp_send_json_success();
    }

    // ── Conditional Logic CRUD ────────────────────────────────────────────────
    public function ajax_save_logic(): void {
        $this->check_nonce();
        global $wpdb;

        $id   = absint( $_POST['id'] ?? 0 );
        $data = [
            'form_id'         => absint( $_POST['form_id'] ?? 0 ),
            'target_id'       => absint( $_POST['target_id'] ?? 0 ),
            'source_question' => absint( $_POST['source_question'] ?? 0 ),
            'source_choice'   => absint( $_POST['source_choice'] ?? 0 ),
            'action'          => in_array( $_POST['action'] ?? 'show', [ 'show', 'hide' ], true ) ? $_POST['action'] : 'show',
            'operator'        => in_array( $_POST['operator'] ?? 'AND', [ 'AND', 'OR' ], true ) ? $_POST['operator'] : 'AND',
        ];

        if ( $id ) {
            $wpdb->update( "{$wpdb->prefix}nben_logic", $data, [ 'id' => $id ] );
        } else {
            $wpdb->insert( "{$wpdb->prefix}nben_logic", $data );
            $id = $wpdb->insert_id;
        }
        wp_send_json_success( [ 'logic_id' => $id ] );
    }

    public function ajax_delete_logic(): void {
        $this->check_nonce();
        global $wpdb;
        $wpdb->delete( "{$wpdb->prefix}nben_logic", [ 'id' => absint( $_POST['id'] ?? 0 ) ] );
        wp_send_json_success();
    }

    // ── Popup CRUD ────────────────────────────────────────────────────────────
    public function ajax_save_popup(): void {
        $this->check_nonce();
        global $wpdb;

        $id = absint( $_POST['id'] ?? 0 );
        $data = [
            'question_id' => absint( $_POST['question_id'] ?? 0 ),
            'choice_id'   => $_POST['choice_id'] ? absint( $_POST['choice_id'] ) : null,
            'title_en'    => sanitize_text_field( wp_unslash( $_POST['title_en'] ?? '' ) ),
            'title_fr'    => sanitize_text_field( wp_unslash( $_POST['title_fr'] ?? '' ) ),
            'content_en'  => wp_kses_post( wp_unslash( $_POST['content_en'] ?? '' ) ),
            'content_fr'  => wp_kses_post( wp_unslash( $_POST['content_fr'] ?? '' ) ),
        ];

        if ( $id ) {
            $wpdb->update( "{$wpdb->prefix}nben_popups", $data, [ 'id' => $id ] );
        } else {
            $wpdb->insert( "{$wpdb->prefix}nben_popups", $data );
            $id = $wpdb->insert_id;
        }
        wp_send_json_success( [ 'popup_id' => $id ] );
    }

    // ── Helper: get questions with choices + logic ─────────────────────────────
    public function ajax_get_questions(): void {
        $this->check_nonce();
        $form_id = absint( $_POST['form_id'] ?? 0 );
        wp_send_json_success( [ 'questions' => $this->get_form_questions( $form_id ) ] );
    }

    public static function get_form_questions( int $form_id ): array {
        global $wpdb;

        $questions = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}nben_questions WHERE form_id=%d ORDER BY sort_order ASC",
            $form_id
        ), ARRAY_A );

        foreach ( $questions as &$q ) {
            $q['id'] = (int) $q['id'];
            $q['choices'] = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}nben_choices WHERE question_id=%d ORDER BY sort_order ASC",
                $q['id']
            ), ARRAY_A );
            $q['logic'] = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}nben_logic WHERE target_id=%d",
                $q['id']
            ), ARRAY_A );
            $q['popup'] = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}nben_popups WHERE question_id=%d AND choice_id IS NULL",
                $q['id']
            ), ARRAY_A );
            foreach ( $q['choices'] as &$c ) {
                $c['popup'] = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}nben_popups WHERE choice_id=%d",
                    $c['id']
                ), ARRAY_A );
            }
        }
        return $questions;
    }
}
