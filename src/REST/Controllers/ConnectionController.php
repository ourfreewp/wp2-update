<?php

namespace WP2\Update\REST\Controllers;

use WP2\Update\REST\AbstractController;
use WP2\Update\Services\Github\ConnectionService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Handles read-only endpoints that report connection and installation status.
 */
final class ConnectionController extends AbstractController {
    private ConnectionService $connectionService;

    public function __construct(ConnectionService $connectionService) {
        parent::__construct();
        $this->connectionService = $connectionService;
    }

    /**
     * Registers the routes for this controller.
     */
    public function register_routes(): void {
        register_rest_route($this->namespace, '/connection-status', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_connection_status'],
            'permission_callback' => $this->permission_callback('wp2_get_connection_status'),
        ]);
    }

    /**
     * Retrieves the overall connection status of the plugin.
     */
    public function get_connection_status(WP_REST_Request $request): WP_REST_Response {
        try {
            $status = $this->connectionService->get_connection_status();
            return $this->respond($status);
        } catch (\Exception $e) {
            return $this->respond(__('Unable to retrieve connection status.', \WP2\Update\Config::TEXT_DOMAIN) . ' ' . $e->getMessage(), 500);
        }
    }
}
