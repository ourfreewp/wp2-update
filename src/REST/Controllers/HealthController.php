<?php

namespace WP2\Update\REST\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP2\Update\Core\GitHub\CredentialService;
use WP2\Update\Core\GitHub\ConnectionService;
use WP2\Update\Core\Health\ConnectivityCheck;
use WP2\Update\Core\Health\DataIntegrityCheck;
use WP2\Update\Core\Health\AbstractCheck;
use WP2\Update\Core\Health\EnvironmentCheck;

final class HealthController extends AbstractRestController {

    private CredentialService $credentialService;
    private ConnectionService $connectionService;
    private ConnectivityCheck $connectivityCheck;
    private DataIntegrityCheck $dataIntegrityCheck;
    private EnvironmentCheck $environmentCheck;
    private array $healthChecks;

    public function __construct(
        CredentialService $credentialService,
        ConnectionService $connectionService,
        ConnectivityCheck $connectivityCheck,
        DataIntegrityCheck $dataIntegrityCheck,
        EnvironmentCheck $environmentCheck,
        ?string $namespace = null
    ) {
        parent::__construct($namespace);
        $this->credentialService = $credentialService;
        $this->connectionService = $connectionService;
        $this->connectivityCheck = $connectivityCheck;
        $this->dataIntegrityCheck = $dataIntegrityCheck;
        $this->environmentCheck = $environmentCheck;

        $this->healthChecks = [
            $this->connectivityCheck,
            $this->dataIntegrityCheck,
            $this->environmentCheck,
        ];
    }

    public function register_routes(): void {
        register_rest_route(
            $this->namespace,
            '/health',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_health_status' ],
                'permission_callback' => $this->permission_callback(),
            ]
        );

        register_rest_route(
            $this->namespace,
            '/logs',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_logs' ],
                'permission_callback' => $this->permission_callback(),
            ]
        );
    }

    public function get_health_status( WP_REST_Request $request ): WP_REST_Response {
        $results = array_map(function (AbstractCheck $check) {
            return $check->run();
        }, $this->healthChecks);

        return $this->respond($results);
    }

    public function get_logs( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $logs = $this->dataIntegrityCheck->check_logs_table($wpdb);
        return $this->respond($logs);
    }
}