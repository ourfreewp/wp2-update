<?php
declare(strict_types=1);

namespace WP2\Update\REST\Controllers;

defined('ABSPATH') || exit;

use WP2\Update\REST\AbstractController;
use WP2\Update\Services\Github\AppService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP2\Update\Utils\CustomException;
use WP2\Update\Utils\Permissions;
use WP2\Update\Data\DTO\AppDTO;
use WP2\Update\Config;
use WP2\Update\Utils\Logger;

/**
 * Class AppsController
 *
 * This class handles REST API endpoints for managing GitHub App connections, including CRUD operations.
 */
final class AppsController extends AbstractController {
    /**
     * @var AppService The service responsible for handling GitHub App operations.
     */
    private AppService $appService;

    /**
     * Constructor for the AppsController class.
     *
     * @param AppService $appService The service responsible for handling GitHub App operations.
     */
    public function __construct(AppService $appService) {
        parent::__construct();
        $this->appService = $appService;
    }

    /**
     * Registers the routes for this controller.
     */
    public function register_routes(): void {
        register_rest_route(Config::REST_NAMESPACE, '/apps', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'list_apps'],
            'permission_callback' => Permissions::callback(Config::CAP_MANAGE, 'wp_rest'),
        ]);

        // Route to create an app
        register_rest_route(Config::REST_NAMESPACE, '/apps/create', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'create_app'],
            'permission_callback' => Permissions::callback(Config::CAP_MANAGE, 'wp_rest'),
        ]);

        register_rest_route(Config::REST_NAMESPACE, '/apps/(?P<id>[0-9a-fA-F-]{36})', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'update_app'],
            'permission_callback' => Permissions::callback(Config::CAP_MANAGE, 'wp_rest'),
        ]);

        register_rest_route(Config::REST_NAMESPACE, '/apps/(?P<id>[0-9a-fA-F-]{36})', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'delete_app'],
            'permission_callback' => Permissions::callback(Config::CAP_MANAGE, 'wp_rest'),
        ]);

        register_rest_route(Config::REST_NAMESPACE, '/apps/connect', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'add_existing_app'],
            'permission_callback' => Permissions::callback(Config::CAP_MANAGE, 'wp_rest'),
        ]);

        register_rest_route(Config::REST_NAMESPACE, '/apps/manifest', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'generate_manifest'],
            'permission_callback' => Permissions::callback(Config::CAP_MANAGE, 'wp_rest'),
        ]);

        register_rest_route(Config::REST_NAMESPACE, '/apps/code', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'exchange_code'],
            'permission_callback' => Permissions::callback(Config::CAP_MANAGE, 'wp_rest'),
        ]);

        register_rest_route(Config::REST_NAMESPACE, '/apps/(?P<id>[0-9a-fA-F-]{36})/status', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_connection_status'],
            'permission_callback' => Permissions::callback(Config::CAP_MANAGE, 'wp_rest'),
        ]);

        register_rest_route(Config::REST_NAMESPACE, '/apps/status', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_connection_status'],
            'permission_callback' => Permissions::callback(Config::CAP_MANAGE, 'wp_rest'),
        ]);
    }

    /**
     * Retrieves a list of all configured apps with caching.
     */
    public function list_apps(WP_REST_Request $request): WP_REST_Response {
        \WP2\Update\Utils\Logger::start('rest:list_apps');
        try {
            $cache_key = Config::TRANSIENT_REPOSITORIES_CACHE . '_apps';
            $cached_apps = \WP2\Update\Utils\Cache::get($cache_key);

            if ($cached_apps) {
                \WP2\Update\Utils\Logger::info('Returning cached apps.');
                return $this->respond($cached_apps);
            }

            $apps = $this->appService->get_all_apps();

            // Cache the result for 5 minutes
            \WP2\Update\Utils\Cache::set($cache_key, $apps, 5 * MINUTE_IN_SECONDS);

            return $this->respond($apps);
        } catch (\Exception $e) {
            \WP2\Update\Utils\Logger::stop('rest:list_apps');
            Logger::error('Failed to list apps.', [
                'exception' => $e->getMessage()
            ]);
            throw new CustomException(__('Failed to list apps. Please check the logs for more details.', Config::TEXT_DOMAIN), 500);
        } finally {
            \WP2\Update\Utils\Logger::stop('rest:list_apps');
        }
    }

    /**
     * Creates a new, empty app record before connecting to GitHub.
     */
    public function create_app(WP_REST_Request $request): WP_REST_Response {
        $name = sanitize_text_field($request->get_param('name'));
        if (empty($name)) {
            return $this->respond(__('App name is required.', Config::TEXT_DOMAIN), 400);
        }

        try {
            $app = $this->appService->create_app_record($name);
            return $this->respond($app, 201);
        } catch (\Exception $e) {
            return $this->respond($e->getMessage(), 500);
        }
    }

    /**
     * Updates an existing app's data.
     */
    public function update_app(WP_REST_Request $request): WP_REST_Response {
        $id = $request->get_param('id');
        $params = $request->get_json_params();

        try {
            $updated_app = $this->appService->update_app_credentials($id, $params);
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
            $this->appService->clear_stored_credentials($id);

            // Invalidate cache for the deleted app
            \WP2\Update\Utils\Cache::delete(Config::TRANSIENT_REPOSITORIES_CACHE . '_' . $id);

            return $this->respond(['message' => __('App deleted successfully.', Config::TEXT_DOMAIN)]);
        } catch (\Exception $e) {
            return $this->respond($e->getMessage(), 500);
        }
    }

    /**
     * Adds an existing GitHub App by securely storing its credentials.
     */
    public function add_existing_app(WP_REST_Request $request): WP_REST_Response {
        $app_id = sanitize_text_field($request->get_param('app_id'));
        $private_key = $request->get_param('private_key');
        $webhook_secret = sanitize_text_field($request->get_param('webhook_secret'));

        if (empty($app_id) || empty($private_key) || empty($webhook_secret)) {
            return $this->respond(__('All fields are required: App ID, Private Key, and Webhook Secret.', Config::TEXT_DOMAIN), 400);
        }

        try {
            // Encrypt and store credentials
            $this->appService->store_manual_credentials($app_id, $private_key, $webhook_secret);

            // Test the connection
            $connection_status = $this->appService->test_connection($app_id);

            if (!$connection_status['success']) {
                return $this->respond(__('Failed to validate the GitHub App credentials.', Config::TEXT_DOMAIN), 400);
            }

            return $this->respond(['message' => __('GitHub App connected successfully.', Config::TEXT_DOMAIN)], 201);
        } catch (\Exception $e) {
            return $this->respond(__('Error connecting GitHub App: ', Config::TEXT_DOMAIN) . $e->getMessage(), 500);
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

    public function generate_manifest(WP_REST_Request $request): WP_REST_Response {
        $app_id = sanitize_text_field($request->get_param('app_id'));
        $name = sanitize_text_field($request->get_param('name'));
        $account_type = sanitize_key($request->get_param('account_type'));
        $org_slug = sanitize_title($request->get_param('organization'));

        if (empty($app_id) || empty($name)) {
            return $this->respond(__('App ID and name are required.', Config::TEXT_DOMAIN), 400);
        }

        try {
            $result = $this->appService->generate_manifest_data($app_id, $name, $account_type, $org_slug);
            return $this->respond($result);
        } catch (\Exception $e) {
            throw new CustomException($e->getMessage(), 500);
        }
    }

    public function exchange_code(WP_REST_Request $request): WP_REST_Response {
        $code = sanitize_text_field($request->get_param('code'));
        $state = sanitize_text_field($request->get_param('state'));

        if (empty($code) || empty($state)) {
            return $this->respond(__('Invalid request. Code and state are required.', Config::TEXT_DOMAIN), 400);
        }

        try {
            $app = $this->appService->exchange_code_for_credentials($code, $state);
            return $this->respond($app);
        } catch (\Exception $e) {
            return $this->respond($e->getMessage(), 400);
        }
    }

    public function manual_setup(WP_REST_Request $request): WP_REST_Response {
        $app_id = sanitize_text_field($request->get_param('app_id'));
        $installation_id = sanitize_text_field($request->get_param('installation_id'));
        $private_key = sanitize_textarea_field($request->get_param('private_key'));

        if (empty($app_id) || empty($installation_id) || empty($private_key)) {
            return $this->respond(__('App ID, installation ID, and private key are required.', Config::TEXT_DOMAIN), 400);
        }

        try {
            $this->appService->store_manual_credentials($app_id, $installation_id, $private_key);
            return $this->respond(['message' => __('Credentials stored successfully.', Config::TEXT_DOMAIN)]);
        } catch (\Exception $e) {
            return $this->respond($e->getMessage(), 500);
        }
    }

    /**
     * Retrieves the connection status for GitHub Apps.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response The response containing the connection status.
     */
    public function get_connection_status(WP_REST_Request $request): WP_REST_Response {
        $app_id = $request->get_param('id');

        if (empty($app_id)) {
            return new WP_REST_Response([
                'code'    => 'wp2_missing_app_id',
                'message' => 'The app ID is required.',
                'data'    => ['status' => 400],
            ], 400);
        }

        try {
            $status = $this->appService->get_connection_status($app_id);
            return new WP_REST_Response($status, 200);
        } catch (CustomException $e) {
            return new WP_REST_Response([
                'code'    => 'wp2_connection_error',
                'message' => $e->getMessage(),
                'data'    => ['status' => 500],
            ], 500);
        }
    }

    /**
     * Retrieves a list of all configured apps with their connection statuses.
     */
    public function list_apps_with_status(WP_REST_Request $request): WP_REST_Response {
        \WP2\Update\Utils\Logger::start('rest:list_apps_with_status');
        try {
            $apps = $this->appService->get_apps_with_status();
            return $this->respond($apps);
        } catch (\Exception $e) {
            \WP2\Update\Utils\Logger::stop('rest:list_apps_with_status');
            Logger::error('Failed to list apps with status.', [
                'exception' => $e->getMessage()
            ]);
            throw new CustomException(__('Failed to list apps with status. Please check the logs for more details.', Config::TEXT_DOMAIN), 500);
        } finally {
            \WP2\Update\Utils\Logger::stop('rest:list_apps_with_status');
        }
    }

    /**
     * Provides a generic permission callback that checks for admin capabilities and a valid nonce.
     *
     * @param string $action The specific nonce to check.
     * @param bool $requireNonce Whether nonce validation is required.
     * @return callable
     */
    protected function permission_callback(string $action, bool $requireNonce = true): callable {
        return parent::permission_callback($action, $requireNonce);
    }
}
