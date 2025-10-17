<?php

namespace WP2\Update\Data;

use WP2\Update\Health\AbstractCheck;
use WP2\Update\Services\Github\AppService;

/**
 * Service to fetch health check data dynamically.
 */
class HealthData {

    private AppService $app_service;
    private array $health_checks;

    public function __construct(AppService $app_service, array $health_checks) {
        $this->app_service = $app_service;
        $this->health_checks = $health_checks;
    }

    /**
     * Retrieves all health checks.
     *
     * @return array The health check data.
     */
    public function get_health_checks(): array {
        \WP2\Update\Utils\Logger::info('Retrieving all health checks.');
        $health_checks = [];

        foreach ($this->health_checks as $check) {
            if (!$check instanceof AbstractCheck) {
                \WP2\Update\Utils\Logger::warning('Invalid health check object provided.', ['check' => get_class($check)]);
                continue; // Skip invalid entries
            }

            $result = $check->run();
            $health_checks[] = [
                'name' => $check->getName(),
                'status' => $result['status'],
                'message' => $result['message'],
            ];
        }

        \WP2\Update\Utils\Logger::info('Health checks retrieved.', ['count' => count($health_checks)]);
        return $health_checks;
    }
}