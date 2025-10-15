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
     * Checks if the user can list apps.
     *
     * @return bool
     */
    private function can_list_apps(): bool {
        return current_user_can('wp2_list_apps');
    }

    /**
     * Checks if the user can create an app.
     *
     * @return bool
     */
    private function can_create_app(): bool {
        return current_user_can('wp2_create_app');
    }

    /**
     * Checks if the user can update an app.
     *
     * @return bool
     */
    private function can_update_app(): bool {
        return current_user_can('wp2_update_app');
    }

    /**
     * Checks if the user can delete an app.
     *
     * @return bool
     */
    private function can_delete_app(): bool {
        return current_user_can('wp2_delete_app');
    }

    /**
     * Registers the routes for this controller.
     */
    public function register_routes(): void {
        register_rest_route($this->namespace, '/apps', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'list_apps'],
                'permission_callback' => [$this, 'can_list_apps'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_app'],
                'permission_callback' => [$this, 'can_create_app'],
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
                'permission_callback' => [$this, 'can_update_app'],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'delete_app'],
                'permission_callback' => [$this, 'can_delete_app'],
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
            $app = $this->connectionService->create_app_record($name);
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

    /**
     * Handles wizard-related actions for GitHub Apps.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response The response.
     */
    public function handle_wizard_action(WP_REST_Request $request): WP_REST_Response {
        $action = $request->get_param('action');
        $response_data = [];

        switch ($action) {
            case 'fetch_steps':
                $response_data = $this->get_wizard_steps();
                break;
            case 'save_progress':
                $progress = $request->get_param('progress');
                $response_data = $this->save_wizard_progress($progress);
                break;
            default:
                return new WP_REST_Response(['error' => 'Invalid action'], 400);
        }

        return new WP_REST_Response($response_data, 200);
    }

    /**
     * Fetches the steps for the GitHub App wizard.
     *
     * @return array The wizard steps.
     */
    private function get_wizard_steps(): array {
        return [
            ['step' => 1, 'title' => 'Connect GitHub Account', 'description' => 'Authorize your GitHub account.'],
            ['step' => 2, 'title' => 'Select Repositories', 'description' => 'Choose repositories to manage.'],
            ['step' => 3, 'title' => 'Configure Settings', 'description' => 'Set up app-specific configurations.'],
        ];
    }

    /**
     * Saves the wizard progress.
     *
     * @param array $progress The progress data.
     * @return array The saved progress confirmation.
     */
    private function save_wizard_progress(array $progress): array {
        // Save progress logic here (e.g., database update).
        return ['status' => 'success', 'message' => 'Progress saved successfully.'];
    }
}
