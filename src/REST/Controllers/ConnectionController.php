<?php

namespace WP2\Update\REST\Controllers;

use WP2\Update\Core\API\ConnectionService;
use WP2\Update\Security\Permissions;
use WP_REST_Request;
use WP_REST_Response;

final class ConnectionController {
    private ConnectionService $connectionService;

    public function __construct(ConnectionService $connectionService) {
        $this->connectionService = $connectionService;
    }

    public static function check_permissions(WP_REST_Request $request): bool {
        return Permissions::current_user_can_manage($request);
    }

    public function get_connection_status(WP_REST_Request $request): WP_REST_Response {
        $status = $this->connectionService->test_connection();

        return new WP_REST_Response([
            'connected' => (bool) ($status['success'] ?? false),
            'message'   => (string) ($status['message'] ?? ''),
        ], 200);
    }

    public function rest_validate_connection(WP_REST_Request $request): WP_REST_Response {
        $result = $this->connectionService->validate_connection();

        return new WP_REST_Response([
            'success' => (bool) ($result['success'] ?? false),
            'message' => (string) ($result['message'] ?? ''),
            'details' => $result['details'] ?? [],
        ], 200);
    }

    public function get_health_status(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response([
            'status' => 'healthy',
            'timestamp' => time(),
        ], 200);
    }
}