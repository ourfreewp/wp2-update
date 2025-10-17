<?php

namespace WP2\Update\REST\Controllers;

use WP2\Update\REST\AbstractController;
use WP2\Update\Utils\Logger;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP2\Update\Config;
use WP2\Update\Utils\Permissions;

/**
 * Class LogsController
 *
 * Handles REST API endpoints for streaming logs.
 */
final class LogsController extends AbstractController {

    /**
     * Registers the routes for this controller.
     */
    public function register_routes(): void {
        register_rest_route(Config::REST_NAMESPACE, '/logs/stream', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'stream_logs'],
            'permission_callback' => Permissions::callback('manage_options'),
        ]);
    }

    /**
     * Streams logs to the client.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response The response containing the log stream.
     */
    public function stream_logs(WP_REST_Request $request): WP_REST_Response {
        try {
            $logs = Logger::get_recent_logs(); // Assuming a method exists to fetch recent logs.
            return new WP_REST_Response($logs, 200);
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'code'    => 'wp2_log_stream_error',
                'message' => $e->getMessage(),
                'data'    => ['status' => 500],
            ], 500);
        }
    }
}