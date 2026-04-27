<?php
defined( 'ABSPATH' ) || exit;
global $wpdb;

$forms   = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}nben_forms ORDER BY id DESC" );
$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : ( $forms[0]->id ?? 0 );
$form    = $form_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}nben_forms WHERE id=%d", $form_id ), ARRAY_A ) : null;
?>
<div class="wrap nben-builder-wrap">
<h1 class="wp-heading-inline"><?php esc_html_e( 'Form Builder', 'nben-tool' ); ?></h1>
<a href="<?php echo esc_url( admin_url( 'admin.php?page=nben-form-builder&new=1' ) ); ?>" class="page-title-action"><?php esc_html_e( 'New Form', 'nben-tool' ); ?></a>
<hr class="wp-header-end">

<div class="nben-builder-layout">

    <!-- ── Sidebar: form list ────────────────────────────────────────────── -->
    <aside class="nben-builder-sidebar">
        <h3><?php esc_html_e( 'Forms', 'nben-tool' ); ?></h3>
        <ul class="nben-form-list">
        <?php foreach ( $forms as $f ) : ?>
            <li class="<?php echo $f->id == $form_id ? 'is-active' : ''; ?>">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=nben-form-builder&form_id=' . $f->id ) ); ?>">
                    <?php echo esc_html( $f->title ); ?>
                    <?php if ( ! $f->status ) echo '<span class="nben-badge nben-badge--draft">Draft</span>'; ?>
                </a>
            </li>
        <?php endforeach; ?>
        </ul>

        <hr>
        <h3><?php esc_html_e( 'Add Field', 'nben-tool' ); ?></h3>
        <div class="nben-field-palette">
            <?php
            $field_types = [
                'radio'    => '⦿ ' . __( 'Radio', 'nben-tool' ),
                'checkbox' => '☑ ' . __( 'Checkbox', 'nben-tool' ),
                'select'   => '▾ ' . __( 'Dropdown', 'nben-tool' ),
                'text'     => 'T ' . __( 'Text', 'nben-tool' ),
                'number'   => '# ' . __( 'Number', 'nben-tool' ),
                'textarea' => '¶ ' . __( 'Textarea', 'nben-tool' ),
                'info'     => '✦ ' . __( 'Info Block', 'nben-tool' ),
            ];
            foreach ( $field_types as $type => $label ) :
            ?>
            <button type="button" class="nben-palette-btn" data-type="<?php echo esc_attr( $type ); ?>">
                <?php echo esc_html( $label ); ?>
            </button>
            <?php endforeach; ?>
        </div>
    </aside>

    <!-- ── Main: form editor ─────────────────────────────────────────────── -->
    <main class="nben-builder-main">

        <?php if ( ! $form ) : ?>
        <div class="nben-builder-empty">
            <p><?php esc_html_e( 'Select a form or create a new one.', 'nben-tool' ); ?></p>
        </div>
        <?php else : ?>

        <!-- Form header -->
        <div class="nben-form-header">
            <div class="nben-form-header__left">
                <input type="text" id="nben-form-title" class="nben-form-title-input" value="<?php echo esc_attr( $form['title'] ); ?>" placeholder="Form title">
                <span class="nben-form-status-badge <?php echo $form['status'] ? 'active' : 'draft'; ?>">
                    <?php echo $form['status'] ? esc_html__( 'Active', 'nben-tool' ) : esc_html__( 'Draft', 'nben-tool' ); ?>
                </span>
            </div>
            <div class="nben-form-header__right">
                <label>
                    <input type="checkbox" id="nben-form-status" <?php checked( $form['status'], 1 ); ?>>
                    <?php esc_html_e( 'Active', 'nben-tool' ); ?>
                </label>
                <button type="button" id="nben-save-form" class="button button-primary" data-form-id="<?php echo esc_attr( $form['id'] ); ?>">
                    <?php esc_html_e( 'Save Form', 'nben-tool' ); ?>
                </button>
                <span class="nben-save-status"></span>
            </div>
        </div>

        <!-- Questions list (sortable) -->
        <div id="nben-questions-list" class="nben-questions-list" data-form-id="<?php echo esc_attr( $form['id'] ); ?>">
            <div class="nben-questions-loading">
                <span class="spinner is-active"></span> <?php esc_html_e( 'Loading questions…', 'nben-tool' ); ?>
            </div>
        </div>

        <!-- Shortcode info -->
        <div class="nben-shortcode-info">
            <?php esc_html_e( 'Shortcode:', 'nben-tool' ); ?>
            <code>[nben_tool form_id="<?php echo esc_html( $form['id'] ); ?>"]</code>
        </div>

        <?php endif; ?>
    </main>

    <!-- ── Right panel: question editor (shown when question selected) ────── -->
    <aside class="nben-builder-editor" id="nben-question-editor" style="display:none">
        <div class="nben-editor-header">
            <h3 id="nben-editor-title"><?php esc_html_e( 'Edit Field', 'nben-tool' ); ?></h3>
            <button type="button" id="nben-editor-close" class="nben-editor-close">✕</button>
        </div>

        <div class="nben-editor-body">
            <input type="hidden" id="nben-q-id" value="">
            <input type="hidden" id="nben-q-form-id" value="<?php echo esc_attr( $form_id ); ?>">

            <div class="nben-field-group">
                <label><?php esc_html_e( 'Field Key (machine name)', 'nben-tool' ); ?></label>
                <input type="text" id="nben-q-key" class="regular-text" placeholder="e.g. primary_hazard">
                <p class="description"><?php esc_html_e( 'Unique identifier. Use lowercase and underscores.', 'nben-tool' ); ?></p>
            </div>

            <div class="nben-field-group">
                <label><?php esc_html_e( 'Field Type', 'nben-tool' ); ?></label>
                <select id="nben-q-type" class="regular-text">
                    <?php foreach ( $field_types as $t => $l ) echo "<option value='$t'>$l</option>"; ?>
                </select>
            </div>

            <div class="nben-tabs">
                <button type="button" class="nben-tab-btn is-active" data-tab="content"><?php esc_html_e( 'Content', 'nben-tool' ); ?></button>
                <button type="button" class="nben-tab-btn" data-tab="choices"><?php esc_html_e( 'Choices', 'nben-tool' ); ?></button>
                <button type="button" class="nben-tab-btn" data-tab="logic"><?php esc_html_e( 'Conditional Logic', 'nben-tool' ); ?></button>
                <button type="button" class="nben-tab-btn" data-tab="popup"><?php esc_html_e( 'Popup', 'nben-tool' ); ?></button>
            </div>

            <!-- Tab: Content -->
            <div class="nben-tab-panel is-active" data-panel="content">
                <div class="nben-field-group">
                    <label><?php esc_html_e( 'Label (English)', 'nben-tool' ); ?> *</label>
                    <textarea id="nben-q-label-en" rows="2" class="large-text"></textarea>
                </div>
                <div class="nben-field-group">
                    <label><?php esc_html_e( 'Label (Français)', 'nben-tool' ); ?></label>
                    <textarea id="nben-q-label-fr" rows="2" class="large-text"></textarea>
                </div>
                <div class="nben-field-group">
                    <label><?php esc_html_e( 'Help text (EN)', 'nben-tool' ); ?></label>
                    <textarea id="nben-q-help-en" rows="3" class="large-text"></textarea>
                </div>
                <div class="nben-field-group">
                    <label><?php esc_html_e( 'Help text (FR)', 'nben-tool' ); ?></label>
                    <textarea id="nben-q-help-fr" rows="3" class="large-text"></textarea>
                </div>
                <div class="nben-field-group">
                    <label>
                        <input type="checkbox" id="nben-q-required" checked>
                        <?php esc_html_e( 'Required', 'nben-tool' ); ?>
                    </label>
                </div>
            </div>

            <!-- Tab: Choices -->
            <div class="nben-tab-panel" data-panel="choices">
                <p class="description"><?php esc_html_e( 'Add answer choices. Drag to reorder.', 'nben-tool' ); ?></p>
                <div id="nben-choices-list" class="nben-choices-editor"></div>
                <button type="button" id="nben-add-choice" class="button">
                    + <?php esc_html_e( 'Add Choice', 'nben-tool' ); ?>
                </button>
            </div>

            <!-- Tab: Conditional Logic -->
            <div class="nben-tab-panel" data-panel="logic">
                <p class="description"><?php esc_html_e( 'Show this question only when:', 'nben-tool' ); ?></p>
                <div id="nben-logic-list" class="nben-logic-editor"></div>
                <button type="button" id="nben-add-logic" class="button">
                    + <?php esc_html_e( 'Add Rule', 'nben-tool' ); ?>
                </button>
            </div>

            <!-- Tab: Popup -->
            <div class="nben-tab-panel" data-panel="popup">
                <div class="nben-field-group">
                    <label><?php esc_html_e( 'Popup Title (EN)', 'nben-tool' ); ?></label>
                    <input type="text" id="nben-popup-title-en" class="large-text">
                </div>
                <div class="nben-field-group">
                    <label><?php esc_html_e( 'Popup Title (FR)', 'nben-tool' ); ?></label>
                    <input type="text" id="nben-popup-title-fr" class="large-text">
                </div>
                <div class="nben-field-group">
                    <label><?php esc_html_e( 'Popup Content (EN)', 'nben-tool' ); ?></label>
                    <textarea id="nben-popup-content-en" rows="6" class="large-text"></textarea>
                </div>
                <div class="nben-field-group">
                    <label><?php esc_html_e( 'Popup Content (FR)', 'nben-tool' ); ?></label>
                    <textarea id="nben-popup-content-fr" rows="6" class="large-text"></textarea>
                </div>
                <input type="hidden" id="nben-popup-id" value="">
            </div>

        </div><!-- .nben-editor-body -->

        <div class="nben-editor-footer">
            <button type="button" id="nben-save-question" class="button button-primary">
                <?php esc_html_e( 'Save Field', 'nben-tool' ); ?>
            </button>
            <button type="button" id="nben-delete-question" class="button nben-btn-danger" style="display:none">
                <?php esc_html_e( 'Delete Field', 'nben-tool' ); ?>
            </button>
        </div>
    </aside>

</div><!-- .nben-builder-layout -->
</div><!-- .wrap -->

<!-- Question row template (used by JS) -->
<script type="text/html" id="nben-question-row-tpl">
<div class="nben-question-row" data-id="{{id}}">
    <span class="nben-drag-handle dashicons dashicons-menu" title="Drag to reorder"></span>
    <div class="nben-question-row__info">
        <span class="nben-q-type-badge">{{field_type}}</span>
        <strong class="nben-q-label">{{label_en}}</strong>
        <em class="nben-q-key">{{question_key}}</em>
    </div>
    <div class="nben-question-row__meta">
        {{has_logic}}{{has_popup}}
    </div>
    <div class="nben-question-row__actions">
        <button type="button" class="button nben-edit-q">Edit</button>
        <button type="button" class="button nben-dupe-q" title="Duplicate">⊕</button>
    </div>
</div>
</script>

<!-- Choice row template -->
<script type="text/html" id="nben-choice-row-tpl">
<div class="nben-choice-row" data-id="{{id}}">
    <span class="nben-drag-handle dashicons dashicons-menu"></span>
    <input type="text" class="nben-choice-key" placeholder="key" value="{{choice_key}}" style="width:90px">
    <input type="text" class="nben-choice-label-en" placeholder="Label EN" value="{{label_en}}" style="flex:1">
    <input type="text" class="nben-choice-label-fr" placeholder="Label FR" value="{{label_fr}}" style="flex:1">
    <input type="text" class="nben-choice-img" placeholder="Image URL" value="{{image_url}}" style="width:160px">
    <button type="button" class="nben-save-choice button">Save</button>
    <button type="button" class="nben-delete-choice button" title="Delete">✕</button>
</div>
</script>

<!-- Logic row template -->
<script type="text/html" id="nben-logic-row-tpl">
<div class="nben-logic-row" data-id="{{id}}">
    <select class="nben-logic-source-q" style="max-width:180px"><option value="">— Question —</option>{{questions_options}}</select>
    <span>answer is</span>
    <select class="nben-logic-source-c" style="max-width:180px"><option value="">— Choice —</option></select>
    <select class="nben-logic-action">
        <option value="show">show this field</option>
        <option value="hide">hide this field</option>
    </select>
    <button type="button" class="nben-save-logic button">Save</button>
    <button type="button" class="nben-delete-logic button" title="Delete">✕</button>
    <input type="hidden" class="nben-logic-id" value="{{id}}">
</div>
</script>
