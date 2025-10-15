<?php

namespace WP2\Update;

use WP2\Update\Utils\Logger;

class Uninstall {
    /**
     * Handles plugin uninstallation.
     */
    public static function on_uninstallation() {
        global $wpdb;

        // Delete plugin options
        delete_option('wp2_update_apps');
        delete_option('wp2_update_packages');

        // Drop custom database tables
        $tables = [
            $wpdb->prefix . 'wp2_update_logs',
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }

        // Log the uninstallation
        Logger::log('INFO', 'WP2 Update plugin uninstalled successfully.');
    }
}