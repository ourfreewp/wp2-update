<?php
declare(strict_types=1);

namespace WP2\Update\REST\Controllers;

defined('ABSPATH') || exit;

use WP2\Update\REST\AbstractController;
use WP2\Update\Utils\Logger as WP2Logger;

final class LogsController extends AbstractController
{
    public function register_routes(): void
    {
        register_rest_route($this->get_namespace(), '/logs', [
            'methods'  => 'GET',
            'callback' => [$this, 'recent'],
            'permission_callback' => function() {
                return current_user_can(\WP2\Update\Config::CAP_VIEW_LOGS);
            },
        ]);
    }

    public function recent(): \WP_REST_Response
    {
        $logs = WP2Logger::get_recent_logs();

        // Simple filtering support: q (search), level, correlation_id, limit
        $q = isset($_GET['q']) ? sanitize_text_field((string) $_GET['q']) : '';
        $level = isset($_GET['level']) ? sanitize_text_field((string) $_GET['level']) : '';
        $cid = isset($_GET['correlation_id']) ? sanitize_text_field((string) $_GET['correlation_id']) : '';
        $limit = isset($_GET['limit']) ? max(1, min(200, (int) $_GET['limit'])) : 50;

        $filtered = array_filter($logs, function($entry) use ($q, $level, $cid) {
            $msg = (string) ($entry['message'] ?? '');
            $ctx = (array) ($entry['context'] ?? []);
            $entryLevel = (string) ($entry['level'] ?? ($ctx['level'] ?? 'info'));
            $entryCid = (string) ($ctx['correlation_id'] ?? '');

            if ($q !== '' && stripos($msg, $q) === false) return false;
            if ($level !== '' && strtolower($entryLevel) !== strtolower($level)) return false;
            if ($cid !== '' && $entryCid !== $cid) return false;
            return true;
        });

        // Normalize shape and enforce limit (most recent last in options; keep tail)
        $normalized = array_map(function($e) {
            $ctx = (array) ($e['context'] ?? []);
            $lvl = (string) ($e['level'] ?? ($ctx['level'] ?? 'info'));
            return [
                'timestamp' => $e['timestamp'] ?? current_time('mysql'),
                'level' => strtoupper($lvl),
                'message' => (string) ($e['message'] ?? ''),
                'context' => $ctx,
            ];
        }, $filtered);

        if (count($normalized) > $limit) {
            $normalized = array_slice($normalized, -1 * $limit);
        }

        return $this->respond(['logs' => array_values($normalized)]);
    }
}
