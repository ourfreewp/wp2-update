<?php

namespace WP2\Update\Health\Checks;

use WP2\Update\Health\AbstractCheck;
use WP2\Update\Config;

/**
 * Health check for verifying data integrity, such as database tables.
 */
class DataIntegrityCheck extends AbstractCheck {

    protected string $label = 'Data Integrity';

    /**
     * Runs the data integrity check.
     *
     * @return array The result of the health check.
     */
    public function run(): array {
        global $wpdb;

        $table_name = $wpdb->prefix . Config::LOGS_TABLE_NAME;

        // Check if the custom logs table exists.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
            return [
                'label'   => $this->label,
                'status'  => 'error',
                'message' => __('The required database table for logging is missing. Please try deactivating and reactivating the plugin.', \WP2\Update\Config::TEXT_DOMAIN),
            ];
        }

        return [
            'label'   => $this->label,
            'status'  => 'pass',
            'message' => __('Database tables are correctly installed.', \WP2\Update\Config::TEXT_DOMAIN),
        ];
    }
}
