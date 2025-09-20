<?php
namespace WP2\Update\Core\API;

/**
 * Registers REST API endpoints for managing backups.
 */
class BackupEndpoints {

    /**
     * Registers the REST API routes.
     */
    public static function register_routes() {
        register_rest_route('wp2-update/v1', '/backups', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'list_backups'],
        ]);

        register_rest_route('wp2-update/v1', '/backups/(?P<id>\d+)', [
            'methods'  => 'DELETE',
            'callback' => [__CLASS__, 'delete_backup'],
        ]);
    }

    /**
     * Lists all backups.
     */
    public static function list_backups() {
        // Placeholder: Replace with actual logic to fetch backups
        return rest_ensure_response([
            ['id' => 1, 'name' => 'Backup 1', 'date' => '2023-10-01'],
            ['id' => 2, 'name' => 'Backup 2', 'date' => '2023-10-02'],
        ]);
    }

    /**
     * Deletes a backup by ID.
     */
    public static function delete_backup($request) {
        $id = $request['id'];
        // Placeholder: Replace with actual logic to delete a backup
        return rest_ensure_response(['success' => true, 'message' => "Backup $id deleted."]);
    }
}