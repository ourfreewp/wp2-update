<?php

namespace WP2\Update\Database;

use WP2\Update\Config;

/**
 * Handles creation and updates of custom database tables.
 */
class Schema {

    /**
     * Creates the custom database tables required by the plugin.
     * This method is intended to be called on plugin activation.
     */
    public static function create_tables(): void {
        global $wpdb;

        $table_name = $wpdb->prefix . Config::LOGS_TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        // SQL statement to create the logs table.
        // dbDelta requires specific formatting (e.g., two spaces after PRIMARY KEY).
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            level varchar(20) NOT NULL,
            message text NOT NULL,
            context longtext,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // We need to load the upgrade.php file to use dbDelta().
        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        dbDelta($sql);
    }
}
