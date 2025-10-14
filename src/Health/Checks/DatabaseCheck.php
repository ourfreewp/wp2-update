<?php

namespace WP2\Update\Health\Checks;

use WP2\Update\Health\AbstractCheck;

/**
 * Health check for verifying database connectivity.
 */
class DatabaseCheck extends AbstractCheck {

    protected string $label = 'Database Connection';

    /**
     * Runs the database connectivity check.
     *
     * @return array The result of the health check.
     */
    public function run(): array {
        global $wpdb;

        $status = 'pass';
        $message = __('Database connection is healthy.', \WP2\Update\Config::TEXT_DOMAIN);

        try {
            $wpdb->query('SELECT 1');
        } catch (\Exception $e) {
            $status = 'error';
            $message = __('Database connection failed: ', \WP2\Update\Config::TEXT_DOMAIN) . $e->getMessage();
        }

        return [
            'label'   => $this->label,
            'status'  => $status,
            'message' => $message,
        ];
    }
}