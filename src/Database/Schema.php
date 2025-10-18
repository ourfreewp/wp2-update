<?php
declare(strict_types=1);

namespace WP2\Update\Database;

defined('ABSPATH') || exit;

final class Schema
{
    /**
     * Creates the necessary database tables for the plugin.
     *
     * Uses dbDelta to create the wp2_update_logs table with appropriate columns and indexes.
     */
    public static function create_tables(): void
    {
        global $wpdb;

        if (!isset($wpdb)) {
            return;
        }

        $table_name = $wpdb->prefix . 'wp2_update_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $schema = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            context varchar(190) DEFAULT '' NOT NULL,
            level varchar(50) DEFAULT '' NOT NULL,
            message longtext NOT NULL,
            extra longtext NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY level (level),
            KEY context (context)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        \dbDelta($schema);
    }
}
