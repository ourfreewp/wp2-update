<?php
declare(strict_types=1);

namespace WP2\Update\Health\Checks;

use WP2\Update\Health\AbstractCheck;
use WP2\Update\Config;
use WP2\Update\Utils\Logger;

/**
 * Health check for verifying database connectivity.
 */
class DatabaseCheck extends AbstractCheck {

    protected string $label = 'Database Connection';

    public function __construct() {
        parent::__construct('database_check');
    }

    /**
     * Runs the database connectivity check.
     *
     * @return array The result of the health check.
     */
    public function run(): array {
        global $wpdb;

        // Log the start of the health check
        Logger::info('Starting DatabaseCheck health check.');

        $status = 'pass';
        $message = __('Database connection is healthy.', Config::TEXT_DOMAIN);

        Logger::start('healthcheck:db_query');
        try {
            $wpdb->query('SELECT 1');
        } catch (\Exception $e) {
            $status = 'error';
            $message = __('Database connection failed: ', Config::TEXT_DOMAIN) . $e->getMessage();
        } finally {
            Logger::stop('healthcheck:db_query');
        }

        if ($status === 'error') {
            Logger::error('DatabaseCheck health check failed.', ['message' => $message]);
        } else {
            Logger::info('DatabaseCheck health check passed.');
        }

        return [
            'label'   => $this->label,
            'status'  => $status,
            'message' => $message,
        ];
    }
}