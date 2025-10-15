<?php

namespace WP2\Update\REST\Controllers;

use WP2\Update\REST\AbstractController;
use WP2\Update\Utils\Logger;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for retrieving plugin logs from the database.
 */
class LogController extends AbstractController
{
    /**
     * Registers the routes for this controller.
     */
    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/logs', [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_logs'],
            'permission_callback' => $this->permission_callback('wp2_view_logs'),
            'args'     => [
                'page' => [
                    'description' => __('The page number of logs to retrieve.', \WP2\Update\Config::TEXT_DOMAIN),
                    'type'        => 'integer',
                    'default'     => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'description' => __('The number of log entries per page.', \WP2\Update\Config::TEXT_DOMAIN),
                    'type'        => 'integer',
                    'default'     => 100,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/logs/stream', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'stream_logs'],
            'permission_callback' => $this->permission_callback('wp2_stream_logs'),
        ]);
    }

    /**
     * Retrieves paginated logs from the database.
     */
    public function get_logs(WP_REST_Request $request): WP_REST_Response
    {
        $page = $request->get_param('page');
        $per_page = $request->get_param('per_page');
        $offset = ($page - 1) * $per_page;

        try {
            $logs = Logger::get_logs($per_page, $offset);
            return $this->respond($logs);
        } catch (\Exception $e) {
            return $this->respond(__("Failed to retrieve logs.", \WP2\Update\Config::TEXT_DOMAIN), 500);
        }
    }

    /**
     * Streams logs in real-time using Server-Sent Events (SSE).
     */
    public function stream_logs(): void
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');

        $lastId = isset($_GET['last_id']) ? (int) $_GET['last_id'] : null;
        $startTime = time();
        $timeout = 300; // 5 minutes

        while (true) {
            if ((time() - $startTime) > $timeout) {
                break;
            }

            $logs = Logger::get_recent_logs($lastId);

            if (!empty($logs)) {
                foreach ($logs as $log) {
                    echo "id: {$log['id']}\n";
                    echo "data: " . json_encode($log) . "\n\n";
                    $lastId = $log['id'];
                }
                ob_flush();
                flush();
            }

            sleep(2); // Poll every 2 seconds
        }
    }
}
