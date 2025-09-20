<?php
namespace WP2\Update\Core\API;

use WP2\Update\Core\GitHubApp\Init as GitHubApp;
use WP2\Update\Core\Webhooks\Handler as WebhookHandler;
use WP_REST_Request;

/**
 * Handles all REST API route registration and callbacks for the plugin.
 */
class REST {

    private GitHubApp $github_app;
    private WebhookHandler $webhook_handler;

    public function __construct(GitHubApp $github_app, WebhookHandler $webhook_handler) {
        $this->github_app = $github_app;
        $this->webhook_handler = $webhook_handler;
    }

    /**
     * Registers all REST API routes.
     */
    public function register_routes() {
        add_action('rest_api_init', [$this, 'setup_routes']);
    }

    /**
     * Defines the API endpoints.
     */
    public function setup_routes() {
        // Permission callback for admin routes
        $permission_callback = function () {
            return current_user_can('manage_options');
        };

        // Get Connection Status
        register_rest_route('wp2-update/v1', '/connection-status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_connection_status'],
            'permission_callback' => $permission_callback,
        ]);

        // Test Connection
        register_rest_route('wp2-update/v1', '/test-connection', [
            'methods' => 'POST',
            'callback' => [$this, 'test_connection'],
            'permission_callback' => $permission_callback,
        ]);

        // Clear Cache and Force Check
        register_rest_route('wp2-update/v1', '/clear-cache-force-check', [
            'methods' => 'POST',
            'callback' => [$this, 'clear_cache_force_check'],
            'permission_callback' => $permission_callback,
        ]);

        // GitHub Webhook (public)
        register_rest_route('wp2-update/v1', '/github/webhooks', [
            'methods' => 'POST',
            'callback' => [$this->webhook_handler, 'handle_webhook'],
            'permission_callback' => '__return_true',
        ]);

        // New Admin Action
        register_rest_route('wp2-update/v1', '/admin-action', [
            'methods' => 'POST',
            'callback' => [$this, 'new_admin_action'],
            'permission_callback' => $permission_callback,
        ]);
    }

    /**
     * Callback for the connection status route.
     * @return \WP_REST_Response
     */
    public function get_connection_status() {
        $status = $this->github_app->get_connection_status();
        return rest_ensure_response($status);
    }

    /**
     * Callback for the test connection route.
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function test_connection(WP_REST_Request $request) {
        $app_slug = sanitize_text_field($request->get_param('app_slug') ?? '');
        if (empty($app_slug)) {
            return rest_ensure_response(['success' => false, 'message' => 'No app slug provided.']);
        }
        $success = $this->github_app->test_connection($app_slug);
        $message = $success ? 'Connection test successful!' : 'Connection test failed. Check settings and error logs.';
        return rest_ensure_response(['success' => $success, 'message' => $message]);
    }

    /**
     * Callback for the clear cache route.
     * @return \WP_REST_Response
     */
    public function clear_cache_force_check() {
        // Clear our custom package cache first
        (new \WP2\Update\Core\Updates\PackageFinder())->clear_cache();
        
        // Clear WordPress update transients
        delete_site_transient('update_themes');
        delete_site_transient('update_plugins');

        // Trigger WordPress to re-check for updates
        wp_update_themes();
        wp_update_plugins();
        
        return rest_ensure_response(['success' => true, 'message' => 'Cache cleared and checks forced.']);
    }

    /**
     * Callback for a new admin action route.
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function new_admin_action(WP_REST_Request $request) {
        $action_param = sanitize_text_field($request->get_param('action_param'));

        if (empty($action_param)) {
            return rest_ensure_response(['success' => false, 'message' => 'Missing required parameter.']);
        }

        // Perform the admin action logic here
        $result = $this->perform_admin_action($action_param);

        if ($result) {
            return rest_ensure_response(['success' => true, 'message' => 'Admin action executed successfully.']);
        } else {
            return rest_ensure_response(['success' => false, 'message' => 'Failed to execute admin action.']);
        }
    }

    /**
     * Helper method to perform the admin action.
     * @param string $action_param
     * @return bool
     */
    private function perform_admin_action(string $action_param): bool {
        // Add the logic for the admin action here
        return true; // Return true if successful, false otherwise
    }
}
