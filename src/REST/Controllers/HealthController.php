<?php

namespace WP2\Update\REST\Controllers;

use WP2\Update\Health\Checks\ConnectivityCheck;
use WP2\Update\Health\Checks\DataIntegrityCheck;
use WP2\Update\Health\Checks\EnvironmentCheck;
use WP2\Update\REST\AbstractController;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for exposing system health status.
 */
final class HealthController extends AbstractController {
    /**
     * @var array An array of health check instances.
     */
    private array $healthChecks;

    public function __construct(
        ConnectivityCheck $connectivityCheck,
        DataIntegrityCheck $dataIntegrityCheck,
        EnvironmentCheck $environmentCheck
    ) {
        parent::__construct();
        $this->healthChecks = [
            'environment' => $environmentCheck,
            'connectivity' => $connectivityCheck,
            'data_integrity' => $dataIntegrityCheck,
        ];
    }

    /**
     * Registers the routes for this controller.
     */
    public function register_routes(): void {
        register_rest_route($this->namespace, '/health', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_health_status'],
            'permission_callback' => $this->permission_callback('wp2_get_health_status'),
        ]);
    }

    /**
     * Runs all registered health checks and returns the results.
     */
    public function get_health_status(WP_REST_Request $request): WP_REST_Response {
        $results = [
            'Environment' => $this->healthChecks['environment']->run(),
            'GitHub Connectivity' => $this->healthChecks['connectivity']->run(),
            'Database' => $this->healthChecks['data_integrity']->run(),
        ];

        // Group checks by a title for better UI presentation
        $grouped_results = [
            [
                'title' => 'System Checks',
                'checks' => [
                    $results['Environment'],
                    $results['Database'],
                ],
            ],
            [
                'title' => 'Integration Checks',
                'checks' => [
                    $results['GitHub Connectivity'],
                ],
            ],
        ];

        return $this->respond($grouped_results);
    }
}
