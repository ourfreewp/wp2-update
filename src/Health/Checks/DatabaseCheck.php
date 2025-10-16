<?php
declare(strict_types=1);

namespace WP2\Update\Health\Checks;

use WP2\Update\Health\AbstractCheck;
use WP2\Update\Config;

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
        $message = __('Database connection is healthy.', Config::TEXT_DOMAIN);

        try {
            $wpdb->query('SELECT 1');
        } catch (\Exception $e) {
            $status = 'error';
            $message = __('Database connection failed: ', Config::TEXT_DOMAIN) . $e->getMessage();
        }

        return [
            'label'   => $this->label,
            'status'  => $status,
            'message' => $message,
        ];
    }
}