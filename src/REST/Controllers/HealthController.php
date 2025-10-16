<?php

namespace WP2\Update\REST\Controllers;

use WP2\Update\Health\Checks\ConnectivityCheck;
use WP2\Update\Health\Checks\DataIntegrityCheck;
use WP2\Update\Health\Checks\EnvironmentCheck;
use WP2\Update\Health\Checks\DatabaseCheck;
use WP2\Update\Health\Checks\RESTCheck;
use WP2\Update\Health\Checks\AssetCheck;
use WP2\Update\REST\AbstractController;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP2\Update\Utils\Logger;
use WP2\Update\Config;

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
        RESTCheck $restCheck,
        AssetCheck $assetCheck
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

        // Improved logging for health check initialization
        Logger::info('HealthController initialized with health checks.', ['checks' => array_keys($this->healthChecks)]);
    }

    /**
     * Registers the routes for this controller.
     */
    public function register_routes(): void {
        register_rest_route($this->namespace, '/health', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_health_status'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        // Improved logging for route registration
        Logger::info('HealthController routes registered.');
    }

    /**
     * Retrieves all registered REST routes belonging to the plugin's namespace.
     *
     * @return array
     */
    private function get_plugin_rest_routes(): array {
        $routes = rest_get_server()->get_routes();
        $pluginRoutes = [];

        foreach ($routes as $route => $details) {
            if (strpos($route, '/' . $this->namespace) === 0) {
                $methods = array_map(fn($method) => $method, $details[0]['methods'] ?? []);
                $pluginRoutes[] = [
                    'route' => $route,
                    'methods' => implode(', ', $methods),
                ];
            }
        }

        return $pluginRoutes;
    }

    /**
     * Runs all registered health checks and returns the results, grouped for troubleshooting.
     */
    public function get_health_status(WP_REST_Request $request): WP_REST_Response {
        $results = [];
        foreach ($this->healthChecks as $key => $checkInstance) {
            $results[$key] = $checkInstance->run();
            Logger::info("Health check executed for {$key}.", ['result' => $results[$key]]);
        }

        $results['rest_endpoints'] = [
            'title' => __('Registered REST Endpoints', Config::TEXT_DOMAIN),
            'data' => $this->get_plugin_rest_routes(),
        ];

        Logger::info('Plugin REST endpoints added to health check results.', ['endpoints' => $results['rest_endpoints']]);

        // Group checks by a title for better UI presentation and operational clarity
        $grouped_results = [
            [
                'title' => __('System Environment', Config::TEXT_DOMAIN),
                'checks' => [
                    $results['environment'],
                    $results['database'],
                ],
            ],
            [
                'title' => __('Application Integrity & Front-end', Config::TEXT_DOMAIN),
                'checks' => [
                    $results['data_integrity'],
                    $results['assets_loaded'],
                    $results['rest_endpoints'],
                ],
            ],
            [
                'title' => __('Integration & Connectivity', Config::TEXT_DOMAIN),
                'checks' => [
                    $results['connectivity'],
                ],
            ],
        ];

        return $this->respond($grouped_results);
    }
}