<?php

namespace WP2\Update\Data;

use WP2\Update\Health\Checks\DatabaseCheck;
use WP2\Update\Health\Checks\ConnectivityCheck;
use WP2\Update\Services\Github\ConnectionService;

/**
 * Service to fetch health check data dynamically.
 */
class HealthData {

    private ConnectionService $connection_service;

    public function __construct(ConnectionService $connection_service) {
        $this->connection_service = $connection_service;
    }

    /**
     * Retrieves all health checks.
     *
     * @return array The health check data.
     */
    public function get_health_checks(): array {
        $checks = [
            new ConnectivityCheck($this->connection_service),
            new DatabaseCheck(),
        ];

        $health_checks = [];

        foreach ($checks as $check) {
            $result = $check->run();
            $health_checks[] = [
                'title' => $result['label'],
                'checks' => $result,
            ];
        }

        return $health_checks;
    }
}