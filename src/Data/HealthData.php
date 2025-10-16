<?php

namespace WP2\Update\Data;

use WP2\Update\Health\AbstractCheck;
use WP2\Update\Health\Checks\DatabaseCheck;
use WP2\Update\Health\Checks\ConnectivityCheck;
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
        $health_checks = [];

        foreach ($this->health_checks as $check) {
            if (!$check instanceof AbstractCheck) {
                continue;
            }

            $result = $check->run();
            $health_checks[] = [
                'title' => $result['label'],
                'checks' => $result,
            ];
        }

        return $health_checks;
    }
}