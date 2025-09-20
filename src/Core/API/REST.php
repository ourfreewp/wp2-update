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

        // Disconnect (now handled by CPT deletion)
        register_rest_route('wp2-update/v1', '/disconnect', [
            'methods' => 'POST',
            'callback' => function() {
                return rest_ensure_response(['success' => false, 'message' => 'Disconnect action is not supported. Please delete the GitHub App CPT entry.']);
            },
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

        // Debug route
        register_rest_route('wp2-update/v1', '/debug', [
            'methods' => 'GET',
            'callback' => [$this, 'debug_route'],
            'permission_callback' => '__return_true',
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
        delete_site_transient('update_themes');
        delete_site_transient('update_plugins');
        wp_update_themes();
        wp_update_plugins();
        return rest_ensure_response(['success' => true, 'message' => 'Cache cleared and checks forced.']);
    }

    /**
     * Callback for the debug route.
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function debug_route(WP_REST_Request $request) {
        // This is now correctly handled by the `WP_REST_Request` object.
        error_log('Nonce received: ' . $request->get_header('X-WP-Nonce'));
        error_log('All headers: ' . json_encode($request->get_headers()));
        return rest_ensure_response(['success' => true]);
    }
}
