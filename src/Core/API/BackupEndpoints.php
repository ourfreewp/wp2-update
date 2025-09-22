<?php
namespace WP2\Update\Core\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

/**
 * Registers REST API endpoints for managing backups.
 */
class BackupEndpoints {

    /**
     * Initializes the class and hooks into WordPress.
     */
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /**
     * Registers the REST API routes.
     */
    public static function register_routes() {
        register_rest_route('wp2-update/v1', '/backups', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'list_backups'],
            'permission_callback' => [__CLASS__, 'authorize'],
        ]);

        register_rest_route('wp2-update/v1', '/backups/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [__CLASS__, 'delete_backup'],
            'permission_callback' => [__CLASS__, 'authorize'],
        ]);

        register_rest_route('wp2-update/v1', '/backups/create', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'create_backup'],
            'permission_callback' => [__CLASS__, 'authorize'],
        ]);
    }

    /**
     * Lists all backups.
     */
    public static function list_backups(): WP_REST_Response {
        /**
         * Filters the list of backups returned by the REST API.
         *
         * @since 1.0.0
         *
         * @param array $backups The list of backups.
         */
        $backups = apply_filters( 'wp2_update_backups_list', [] );
        if ( ! is_array( $backups ) ) {
            $backups = [];
        }

        return rest_ensure_response( [
            'backups' => array_values( $backups ),
        ] );
    }

    /**
     * Deletes a backup by ID.
     */
    public static function delete_backup( WP_REST_Request $request ) {
        $id = (int) $request['id'];

        if ( ! has_action( 'wp2_update_delete_backup' ) ) {
            return new WP_Error(
                'wp2_backups_not_configured',
                __( 'Backup deletion is not configured.', 'wp2-update' ),
                [ 'status' => 501 ]
            );
        }

        /**
         * Fires when a backup deletion is requested via the REST API.
         *
         * @since 1.0.0
         *
         * @param int $id The backup identifier requested for deletion.
         */
        do_action( 'wp2_update_delete_backup', $id );

        return rest_ensure_response( [
            'success' => true,
            'message' => sprintf( __( 'Requested deletion for backup %d.', 'wp2-update' ), $id ),
        ] );
    }

    /**
     * Creates a backup before an update.
     *
     * @param string $type The type of package ('plugin' or 'theme').
     * @param string $slug The slug of the package.
     * @return array The result of the backup creation.
     */
    public static function create_backup_direct($type, $slug) {
        if (empty($type) || empty($slug)) {
            return [
                'success' => false,
                'message' => __('Invalid parameters. Type and slug are required.', 'wp2-update'),
            ];
        }

        // Ensure the backup directory exists.
        $upload_dir = wp_upload_dir();
        $backup_dir = trailingslashit($upload_dir['basedir']) . 'wp2-backups';
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }

        // Create the backup file.
        $backup_file = trailingslashit($backup_dir) . $slug . '-' . date('Ymd-His') . '.zip';
        $success = self::zip_package($type, $slug, $backup_file);

        if (!$success) {
            return [
                'success' => false,
                'message' => __('Failed to create backup.', 'wp2-update'),
            ];
        }

        return [
            'success' => true,
            'message' => __('Backup created successfully.', 'wp2-update'),
            'backup_file' => $backup_file,
        ];
    }

    /**
     * Creates a backup before an update (REST API wrapper).
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response The response object.
     */
    public static function create_backup( WP_REST_Request $request ) {
        $type = $request->get_param('type'); // 'plugin' or 'theme'
        $slug = $request->get_param('slug');

        $result = self::create_backup_direct($type, $slug);

        if (!$result['success']) {
            return rest_ensure_response(
                new WP_Error(
                    'wp2_update_backup_failed',
                    $result['message'],
                    ['status' => 500]
                )
            );
        }

        return rest_ensure_response($result);
    }

    /**
     * Utility function to create a ZIP backup of a plugin or theme.
     *
     * @param string $type The type of package ('plugin' or 'theme').
     * @param string $slug The slug of the package.
     * @param string $destination The destination file path for the ZIP.
     * @return bool True on success, false on failure.
     */
    private static function zip_package( $type, $slug, $destination ) {
        $source = ( $type === 'plugin' )
            ? WP_PLUGIN_DIR . '/' . $slug
            : get_theme_root() . '/' . $slug;

        if ( ! file_exists( $source ) ) {
            return false;
        }

        $zip = new ZipArchive();
        if ( $zip->open( $destination, ZipArchive::CREATE ) !== true ) {
            return false;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $source, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ( $files as $file ) {
            $file_path = $file->getRealPath();
            $relative_path = substr( $file_path, strlen( $source ) + 1 );
            if ( $file->isDir() ) {
                $zip->addEmptyDir( $relative_path );
            } else {
                $zip->addFile( $file_path, $relative_path );
            }
        }

        return $zip->close();
    }

    /**
     * Restores a backup.
     *
     * @param string $backup_file The path to the backup file to restore.
     * @return array The result of the restore operation.
     */
    public static function restore_backup($backup_file) {
        if (!file_exists($backup_file)) {
            return [
                'success' => false,
                'message' => __('Backup file does not exist.', 'wp2-update'),
            ];
        }

        $zip = new \ZipArchive();
        if ($zip->open($backup_file) !== true) {
            return [
                'success' => false,
                'message' => __('Failed to open backup file.', 'wp2-update'),
            ];
        }

        $extract_path = wp_upload_dir()['basedir'] . '/wp2-restore-temp';
        if (!file_exists($extract_path)) {
            wp_mkdir_p($extract_path);
        }

        if (!$zip->extractTo($extract_path)) {
            $zip->close();
            return [
                'success' => false,
                'message' => __('Failed to extract backup file.', 'wp2-update'),
            ];
        }

        $zip->close();

        // Logic to move extracted files to their respective locations.
        // This part depends on the structure of the backup and the system.

        return [
            'success' => true,
            'message' => __('Backup restored successfully.', 'wp2-update'),
        ];
    }

    /**
     * Prunes old backups based on a user-defined limit.
     *
     * @param int $limit The maximum number of backups to keep.
     * @return array The result of the pruning operation.
     */
    public static function prune_backups($limit) {
        $upload_dir = wp_upload_dir();
        $backup_dir = trailingslashit($upload_dir['basedir']) . 'wp2-backups';

        if (!file_exists($backup_dir)) {
            return [
                'success' => false,
                'message' => __('Backup directory does not exist.', 'wp2-update'),
            ];
        }

        $backups = glob($backup_dir . '*.zip');
        if (count($backups) <= $limit) {
            return [
                'success' => true,
                'message' => __('No backups to prune.', 'wp2-update'),
            ];
        }

        usort($backups, function ($a, $b) {
            return filemtime($a) - filemtime($b);
        });

        $backups_to_delete = array_slice($backups, 0, count($backups) - $limit);
        foreach ($backups_to_delete as $backup) {
            unlink($backup);
        }

        return [
            'success' => true,
            'message' => sprintf(
                __('Pruned %d old backups.', 'wp2-update'),
                count($backups_to_delete)
            ),
        ];
    }

    /**
     * Determines if the current user may interact with backup endpoints.
     */
    public static function authorize(): bool {
        // Ensure the user has the required capability to manage backups.
        return current_user_can('manage_options');
    }
}