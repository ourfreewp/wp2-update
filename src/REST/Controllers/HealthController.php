<?php

namespace WP2\Update\REST\Controllers;

use WP2\Update\Health\Checks\ConnectivityCheck;
use WP2\Update\Health\Checks\DataIntegrityCheck;
use WP2\Update\Health\Checks\EnvironmentCheck;
use WP2\Update\Health\Checks\DatabaseCheck;
use WP2\Update\Health\Checks\RESTCheck; // New
use WP2\Update\Health\Checks\AssetCheck; // New
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
        EnvironmentCheck $environmentCheck,
        DatabaseCheck $databaseCheck,
        RESTCheck $restCheck, // New dependency
        AssetCheck $assetCheck // New dependency
    ) {
        parent::__construct();
        $this->healthChecks = [
            'environment' => $environmentCheck,
            'database' => $databaseCheck,
            'data_integrity' => $dataIntegrityCheck,
            'connectivity' => $connectivityCheck,
            'rest_endpoints' => $restCheck,
            'assets_loaded' => $assetCheck,
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
     * Retrieves all registered REST routes.
     *
     * @return array
     */
    private function get_rest_routes(): array {
        $routes = rest_get_server()->get_routes();
        $formattedRoutes = [];

        foreach ($routes as $route => $details) {
            $methods = array_map(fn($method) => $method, $details[0]['methods'] ?? []);
            $formattedRoutes[] = [
                'route' => $route,
                'methods' => implode(', ', $methods),
            ];
        }

        return $formattedRoutes;
    }

    /**
     * Runs all registered health checks and returns the results, grouped for troubleshooting.
     */
    public function get_health_status(WP_REST_Request $request): WP_REST_Response {
        $results = [];
        foreach ($this->healthChecks as $key => $checkInstance) {
             $results[$key] = $checkInstance->run();
        }

        // Add REST endpoints to the health check results
        $results['rest_endpoints'] = [
            'title' => __('Registered REST Endpoints', \WP2\Update\Config::TEXT_DOMAIN),
            'data' => $this->get_rest_routes(),
        ];

        // Group checks by a title for better UI presentation and operational clarity
        $grouped_results = [
            [
                'title' => __('System Environment', \WP2\Update\Config::TEXT_DOMAIN),
                'checks' => [
                    $results['environment'],
                    $results['database'],
                ],
            ],
            [
                'title' => __('Application Integrity & Front-end', \WP2\Update\Config::TEXT_DOMAIN),
                'checks' => [
                    $results['data_integrity'],
                    $results['assets_loaded'],
                    $results['rest_endpoints'],
                ],
            ],
            [
                'title' => __('Integration & Connectivity', \WP2\Update\Config::TEXT_DOMAIN),
                'checks' => [
                    $results['connectivity'],
                ],
            ],
        ];

        return $this->respond($grouped_results);
    }
}