<?php
declare(strict_types=1);

namespace WP2\Update\REST\Controllers;

defined('ABSPATH') || exit;

use WP2\Update\REST\AbstractController;
use WP2\Update\Services\BackupService;
use WP_REST_Request;
use WP2\Update\Config;

final class BackupController extends AbstractController
{
    private BackupService $backup;

    public function __construct()
    {
        parent::__construct();
        $this->backup = new BackupService();
    }

    public function register_routes(): void
    {
        register_rest_route($this->get_namespace(), '/backups', [
            'methods'  => 'GET',
            'callback' => [$this, 'list'],
            'permission_callback' => function($request) {
                return current_user_can(Config::CAP_MANAGE);
            },
        ]);

        register_rest_route($this->get_namespace(), '/backups/restore', [
            'methods'  => 'POST',
            'callback' => [$this, 'restore'],
            'permission_callback' => function($request) {
                return current_user_can(Config::CAP_RESTORE_BACKUPS);
            },
            'args' => [
                'file' => [ 'required' => true, 'type' => 'string' ],
                'type' => [ 'required' => true, 'type' => 'string', 'enum' => ['plugin', 'theme'] ],
            ],
        ]);

        register_rest_route($this->get_namespace(), '/backups/download', [
            'methods'  => 'GET',
            'callback' => [$this, 'download'],
            'permission_callback' => function() {
                return current_user_can(Config::CAP_RESTORE_BACKUPS);
            },
            'args' => [
                'file' => [ 'required' => true, 'type' => 'string' ],
            ],
        ]);

        // Delete a single backup file
        register_rest_route($this->get_namespace(), '/backups/delete', [
            'methods'  => 'DELETE',
            'callback' => [$this, 'delete'],
            'permission_callback' => function() {
                return current_user_can(Config::CAP_RESTORE_BACKUPS);
            },
            'args' => [
                'file' => [ 'required' => true, 'type' => 'string' ],
            ],
        ]);

        // Bulk delete backup files
        register_rest_route($this->get_namespace(), '/backups/delete-bulk', [
            'methods'  => 'POST',
            'callback' => [$this, 'delete_bulk'],
            'permission_callback' => function() {
                return current_user_can(Config::CAP_RESTORE_BACKUPS);
            },
            'args' => [
                'files' => [ 'required' => true, 'type' => 'array' ],
            ],
        ]);
    }

    public function list(WP_REST_Request $request)
    {
        $filter = $request->get_param('q');
        $limit = (int) ($request->get_param('limit') ?? 100);
        $limit = max(1, min(500, $limit));
        $list = $this->backup->list_backups(is_string($filter) ? $filter : null);
        if (count($list) > $limit) {
            $list = array_slice($list, -1 * $limit);
        }
        return $this->respond(['backups' => array_values($list)]);
    }

    /**
     * Verifies the nonce for a given action.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return bool True if the nonce is valid, false otherwise.
     */
    private function verify_action_nonce(WP_REST_Request $request): bool
    {
        $nonce = $request->get_header('X-WP-Nonce');
        return wp_verify_nonce($nonce, 'wp2_update_action');
    }

    public function restore(WP_REST_Request $request)
    {
        if (!$this->verify_action_nonce($request)) {
            return $this->respond(['message' => 'Invalid nonce.'], 403);
        }

        $file = (string) $request->get_param('file');
        $type = (string) $request->get_param('type');
        try {
            $ok = $this->backup->restore_backup($file, $type);
            return $this->respond(['restored' => $ok]);
        } catch (\Throwable $e) {
            return $this->respond(['message' => $e->getMessage()], 400);
        }
    }

    public function download(WP_REST_Request $request)
    {
        if (!$this->verify_action_nonce($request)) {
            return $this->respond(['message' => 'Invalid nonce.'], 403);
        }

        $file = basename((string) $request->get_param('file'));
        try {
            // Resolve path safely
            $dir = (new BackupService())->ensure_backup_dir();
            $path = $dir . $file;
            if (!is_file($path)) {
                return $this->respond('Backup file not found.', 404);
            }

            // Output file
            nocache_headers();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . esc_attr($file) . '"');
            header('Content-Length: ' . (string) filesize($path));
            readfile($path);
            exit;
        } catch (\Throwable $e) {
            return $this->respond(['message' => $e->getMessage()], 400);
        }
    }

    public function delete(WP_REST_Request $request)
    {
        if (!$this->verify_action_nonce($request)) {
            return $this->respond(['message' => 'Invalid nonce.'], 403);
        }

        $file = basename((string) $request->get_param('file'));
        try {
            $deleted = $this->backup->delete_backup($file);
            if (!$deleted) {
                return $this->respond(['deleted' => false, 'message' => 'File not found or could not be deleted.'], 404);
            }
            return $this->respond(['deleted' => true]);
        } catch (\Throwable $e) {
            return $this->respond(['message' => $e->getMessage()], 400);
        }
    }

    public function delete_bulk(WP_REST_Request $request)
    {
        if (!$this->verify_action_nonce($request)) {
            return $this->respond(['message' => 'Invalid nonce.'], 403);
        }

        $files = (array) $request->get_param('files');
        $files = array_map(function($f) { return basename((string) $f); }, $files);
        $results = [];

        foreach ($files as $file) {
            try {
                $deleted = $this->backup->delete_backup($file);
                $results[] = ['file' => $file, 'deleted' => $deleted];
            } catch (\Throwable $e) {
                $results[] = ['file' => $file, 'deleted' => false, 'error' => $e->getMessage()];
            }
        }

        return $this->respond(['results' => $results]);
    }
}
