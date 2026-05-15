<?php

namespace NBEN;

defined('ABSPATH') || exit;

class Installer
{
    /**
     * Tables we create:
     *  nben_forms        – form definitions (one row per form)
     *  nben_questions    – questions belonging to a form
     *  nben_choices      – answer choices for each question
     *  nben_logic        – conditional-show rules
     *  nben_popups       – popup/tooltip content per question
     */
    public static function activate(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = [];

        // Forms
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}nben_forms (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title         VARCHAR(255)    NOT NULL DEFAULT '',
            slug          VARCHAR(255)    NOT NULL DEFAULT '',
            description   TEXT,
            status        TINYINT(1)      NOT NULL DEFAULT 1,
            created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset;";

        // Questions
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}nben_questions (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id         BIGINT UNSIGNED NOT NULL,
            parent_id       BIGINT UNSIGNED DEFAULT NULL COMMENT 'sub-question parent',
            question_key    VARCHAR(100)    NOT NULL DEFAULT '' COMMENT 'machine name e.g. q_hazard',
            label_en        TEXT            NOT NULL,
            label_fr        TEXT,
            help_text_en    TEXT,
            help_text_fr    TEXT,
            field_type      VARCHAR(50)     NOT NULL DEFAULT 'radio' COMMENT 'radio|checkbox|select|text|number|textarea|info',
            required        TINYINT(1)      NOT NULL DEFAULT 1,
            sort_order      SMALLINT        NOT NULL DEFAULT 0,
            settings        JSON,
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY sort_order (sort_order)
        ) $charset;";

        // Choices
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}nben_choices (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            question_id     BIGINT UNSIGNED NOT NULL,
            choice_key      VARCHAR(100)    NOT NULL DEFAULT '',
            label_en        VARCHAR(500)    NOT NULL,
            label_fr        VARCHAR(500),
            description_en  TEXT,
            description_fr  TEXT,
            image_url       VARCHAR(1000),
            sort_order      SMALLINT        NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY question_id (question_id)
        ) $charset;";

        // Conditional Logic  (show question X when question Y answer = choice Z)
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}nben_logic (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id         BIGINT UNSIGNED NOT NULL,
            target_id       BIGINT UNSIGNED NOT NULL COMMENT 'question to show/hide',
            source_question BIGINT UNSIGNED NOT NULL COMMENT 'question that drives the rule',
            source_choice   BIGINT UNSIGNED NOT NULL COMMENT 'choice that triggers the rule',
            action          VARCHAR(20)     NOT NULL DEFAULT 'show' COMMENT 'show|hide',
            operator        VARCHAR(10)     NOT NULL DEFAULT 'AND',
            sort_order      SMALLINT        NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY target_id (target_id)
        ) $charset;";

        // Popups / tooltips
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}nben_popups (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            question_id     BIGINT UNSIGNED NOT NULL,
            choice_id       BIGINT UNSIGNED DEFAULT NULL COMMENT 'NULL = popup on question label',
            title_en        VARCHAR(500),
            title_fr        VARCHAR(500),
            content_en      LONGTEXT,
            content_fr      LONGTEXT,
            PRIMARY KEY (id),
            KEY question_id (question_id)
        ) $charset;";

        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        foreach ($sql as $q) {
            dbDelta($q);
        }

        // Store DB version for future migrations
        update_option('nben_db_version', NBEN_VERSION);

        // Seed default form if none exist
        self::maybe_seed_default_form();

        // Flush rewrite rules after CPT registration
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    /**
     * Insert a starter form so the admin sees something on first run.
     */
    private static function maybe_seed_default_form(): void
    {
        global $wpdb;
        $table = $wpdb->prefix.'nben_forms';
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($count > 0) {
            return;
        }

        $wpdb->insert($table, [
            'title' => 'NbS Cost Estimation Tool',
            'slug' => 'nbs-cost-estimation',
            'description' => 'Guides users to identify and estimate costs of Nature-based Solutions.',
            'status' => 1,
        ]);
    }
}
