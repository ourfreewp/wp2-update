<?php

namespace WP2\Update\REST\Controllers;

use WP2\Update\REST\AbstractController;
use WP2\Update\Services\Github\ConnectionService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for managing GitHub App connections (CRUD operations).
 */
final class AppsController extends AbstractController {
    private ConnectionService $connectionService;

    public function __construct(ConnectionService $connectionService) {
        parent::__construct();
        $this->connectionService = $connectionService;
    }

    /**
     * Registers the routes for this controller.
     */
    public function register_routes(): void {
        register_rest_route($this->namespace, '/apps', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'list_apps'],
                'permission_callback' => $this->permission_callback('wp2_list_apps'),
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_app'],
                'permission_callback' => $this->permission_callback('wp2_create_app'),
            ],
        ]);

        register_rest_route($this->namespace, '/apps/(?P<id>[\w-]+)', [
            'args' => [
                'id' => [
                    'description' => __('The unique identifier for the app.', \WP2\Update\Config::TEXT_DOMAIN),
                    'type'        => 'string',
                    'required'    => true,
                ],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update_app'],
                'permission_callback' => $this->permission_callback('wp2_update_app'),
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'delete_app'],
                'permission_callback' => $this->permission_callback('wp2_delete_app'),
            ],
        ]);
    }

    /**
     * Retrieves a list of all configured apps.
     */
    public function list_apps(): WP_REST_Response {
        $apps = $this->connectionService->get_app_summaries();
        return $this->respond($apps);
    }

    /**
     * Creates a new, empty app record before connecting to GitHub.
     */
    public function create_app(WP_REST_Request $request): WP_REST_Response {
        $name = sanitize_text_field($request->get_param('name'));
        if (empty($name)) {
            return $this->respond(__('App name is required.', \WP2\Update\Config::TEXT_DOMAIN), 400);
        }

        try {
            $app = $this->connectionService->create_placeholder_app($name);
            return $this->respond($app, 201);
        } catch (\Exception $e) {
            return $this->respond(__('Failed to create app: ', \WP2\Update\Config::TEXT_DOMAIN) . $e->getMessage(), 500);
        }
    }

    /**
     * Updates an existing app's data.
     */
    public function update_app(WP_REST_Request $request): WP_REST_Response {
        $id = $request->get_param('id');
        $params = $request->get_json_params();

        try {
            $updated_app = $this->connectionService->update_app_credentials($id, $params);
            return $this->respond($updated_app);
        } catch (\Exception $e) {
            return $this->respond($e->getMessage(), 404);
        }
    }

    /**
     * Deletes an app record.
     */
    public function delete_app(WP_REST_Request $request): WP_REST_Response {
        $id = $request->get_param('id');

        try {
            $this->connectionService->clear_stored_credentials($id);

            // Invalidate cache for the deleted app
            \WP2\Update\Utils\Cache::delete(\WP2\Update\Config::TRANSIENT_REPOSITORIES_CACHE . '_' . $id);

            return $this->respond(['message' => __('App deleted successfully.', \WP2\Update\Config::TEXT_DOMAIN)]);
        } catch (\Exception $e) {
            return $this->respond($e->getMessage(), 500);
        }
    }
}
