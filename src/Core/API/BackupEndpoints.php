<?php
namespace WP2\Update\Core\API;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

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
     * Determines if the current user may interact with backup endpoints.
     */
    public static function authorize(): bool {
        // Ensure the user has the required capability to manage backups.
        return current_user_can('manage_options');
    }
}