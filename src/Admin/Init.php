<?php

namespace WP2\Update\Admin;

use WP2\Update\Core\API\GitHubApp\Init as GitHubApp;
use WP2\Update\Core\API\Service as GitHubService;
use WP2\Update\Core\Updates\PackageFinder;
use WP2\Update\Utils\SharedUtils;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Registers the simplified admin UI and handles form submissions.
 */
class Init
{
    private GitHubService $githubService;
    private PackageFinder $packages;
    private Pages $pages;
    private GitHubApp $githubApp;

    public function __construct(GitHubService $githubService, PackageFinder $packages, SharedUtils $utils, GitHubApp $githubApp)
    {
        $this->githubService = $githubService;
        $this->packages      = $packages;
        $this->githubApp     = $githubApp;
        $this->pages         = new Pages($githubService, $packages, $githubApp);
    }

    /**
     * Register admin-facing hooks.
     */
    public function register_hooks(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_wp2_save_github_app', [$this, 'handle_save_credentials']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    /**
     * Adds the single plugin settings page.
     */
    public function register_menu(): void
    {
        add_menu_page(
            __('WP2 Updates', 'wp2-update'),
            __('WP2 Updates', 'wp2-update'),
            'manage_options',
            'wp2-update',
            [$this->pages, 'render'],
            'dashicons-cloud'
        );
    }

    /**
     * Persist GitHub App credentials submitted from the admin form.
     */
    public function handle_save_credentials(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'wp2-update'));
        }

        check_admin_referer('wp2_save_github_app');

        $appName        = isset($_POST['wp2_app_name']) ? sanitize_text_field(wp_unslash($_POST['wp2_app_name'])) : '';
        $appId          = isset($_POST['wp2_app_id']) ? absint($_POST['wp2_app_id']) : 0;
        $installationId = isset($_POST['wp2_installation_id']) ? absint($_POST['wp2_installation_id']) : 0;
        $privateKey     = isset($_POST['wp2_private_key']) ? sanitize_textarea_field(wp_unslash($_POST['wp2_private_key'])) : '';

        $this->githubService->store_app_credentials([
            'name'            => $appName,
            'app_id'          => $appId,
            'installation_id' => $installationId,
            'private_key'     => $privateKey,
        ]);

        $redirect = add_query_arg(
            [
                'page'       => 'wp2-update',
                'wp2_notice' => 'saved',
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Registers REST API routes for the plugin.
     */
    public function register_rest_routes(): void
    {
        register_rest_route('wp2-update/v1', '/connection-status', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_connection_status'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        register_rest_route('wp2-update/v1', '/run-update-check', [
            'methods'             => 'POST',
            'callback'            => [$this, 'rest_run_update_check'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        register_rest_route('wp2-update/v1', '/test-connection', [
            'methods'             => 'POST',
            'callback'            => [$this, 'rest_test_connection'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        register_rest_route('wp2-update/v1', '/create-github-app', [
            'methods'             => 'POST',
            'callback'            => [$this, 'create_github_app'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        register_rest_route('wp2-update/v1', '/validate-connection', [
            'methods'             => 'POST',
            'callback'            => [$this, 'validate_connection'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        register_rest_route('wp2-update/v1', '/sync-packages', [
            'methods'             => 'POST',
            'callback'            => [$this, 'sync_packages'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        register_rest_route('wp2-update/v1', '/manage-packages', [
            'methods'             => 'POST',
            'callback'            => [$this, 'manage_packages'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        register_rest_route('wp2-update/v1', '/webhook', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_webhook'],
            'permission_callback' => '__return_true', // Webhooks do not require authentication
        ]);
    }

    /**
     * Checks if the current user has the required permissions.
     */
    public function check_permissions(): bool
    {
        $nonce = $_REQUEST['wp2_update_nonce'] ?? '';
        return current_user_can('manage_options') && wp_verify_nonce($nonce, 'wp2_update_nonce');
    }

    /**
     * Handles the connection status retrieval.
     */
    public function get_connection_status(WP_REST_Request $request): WP_REST_Response
    {
        $status = $this->githubApp->get_connection_status();

        return new WP_REST_Response([
            'connected' => $status['connected'],
            'message' => $status['message'],
        ]);
    }

    /**
     * REST API callback for running update checks.
     */
    public function rest_run_update_check(WP_REST_Request $request): WP_REST_Response
    {
        wp_update_plugins();
        wp_update_themes();

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Update check completed successfully.', 'wp2-update'),
        ]);
    }

    /**
     * REST API callback for testing the connection.
     */
    public function rest_test_connection(WP_REST_Request $request): WP_REST_Response
    {
        $result = $this->githubApp->test_connection();

        return new WP_REST_Response([
            'success' => $result['success'],
            'message' => $result['message'],
        ]);
    }

    /**
     * REST API callback for validating the GitHub App connection.
     */
    public function validate_connection(WP_REST_Request $request): WP_REST_Response
    {
        $jwtResult = $this->githubService->mintJWT();
        $apiResult = $this->githubService->testAPIConnection();

        $status = [
            'jwt' => $jwtResult,
            'api' => $apiResult,
        ];

        return new WP_REST_Response($status, 200);
    }

    /**
     * REST API callback for synchronizing packages.
     */
    public function sync_packages(WP_REST_Request $request): WP_REST_Response
    {
        $repositories = $this->githubService->fetchRepositories();

        if (empty($repositories)) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => __('No repositories found.', 'wp2-update'),
            ], 404);
        }

        return new WP_REST_Response([
            'status' => 'success',
            'repositories' => $repositories,
        ], 200);
    }

    /**
     * Manages package versions (e.g., update, rollback).
     */
    public function manage_packages(WP_REST_Request $request): WP_REST_Response
    {
        $action = $request->get_param('action');
        $package = $request->get_param('package');
        $version = $request->get_param('version');

        $result = $this->githubService->manage_package($action, $package, $version);

        if ($result['success']) {
            return new WP_REST_Response([
                'status' => 'success',
                'message' => $result['message'],
            ], 200);
        }

        return new WP_REST_Response([
            'status' => 'error',
            'message' => $result['message'],
        ], 400);
    }

    /**
     * Enqueue admin scripts and styles using Vite loader.
     */
    public function enqueue_admin_scripts(): void
    {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'toplevel_page_wp2-update') {
            $this->enqueue_vite_assets('admin-main');
        }
    }

    /**
     * Enqueue assets from Vite's manifest.json.
     */
    public function enqueue_vite_assets(string $entry): void
    {
        $manifest_path = plugin_dir_path(__FILE__) . '../../dist/manifest.json';
        error_log('enqueue_vite_assets called. Manifest path: ' . $manifest_path);

        if (!file_exists($manifest_path)) {
            error_log('Vite manifest not found: ' . $manifest_path);
            return;
        }

        $manifest = json_decode(file_get_contents($manifest_path), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Failed to decode Vite manifest JSON: ' . json_last_error_msg());
            return;
        }

        if (!isset($manifest[$entry])) {
            error_log('Entry not found in Vite manifest: ' . $entry);
            return;
        }

        $entry_data = $manifest[$entry];
        error_log('Enqueuing entry: ' . $entry . ' with data: ' . print_r($entry_data, true));

        // Enqueue the main JS file
        if (isset($entry_data['file'])) {
            wp_enqueue_script(
                'wp2-update-' . $entry,
                plugins_url('/dist/' . $entry_data['file'], __FILE__),
                [],
                null,
                true
            );
            error_log('Enqueued JS file: ' . $entry_data['file']);
        }

        // Enqueue CSS files
        if (isset($entry_data['css'])) {
            foreach ($entry_data['css'] as $css_file) {
                wp_enqueue_style(
                    'wp2-update-' . $entry . '-css',
                    plugins_url('/dist/' . $css_file, __FILE__),
                    [],
                    null
                );
                error_log('Enqueued CSS file: ' . $css_file);
            }
        }
    }

    /**
     * Handles incoming webhook requests with enhanced validation.
     */
    public function handle_webhook(WP_REST_Request $request): WP_REST_Response
    {
        $signature = $request->get_header('X-Hub-Signature-256');
        $payload = $request->get_body();

        // Validate the signature
        $credentials = $this->githubService->get_stored_credentials();
        $secret = $credentials['webhook_secret'] ?? ''; // Use the webhook secret instead of the private key
        $calculatedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        if (!$signature || !hash_equals($calculatedSignature, $signature)) {
            return new WP_REST_Response(
                ['success' => false, 'message' => 'Invalid or missing signature.'],
                403
            );
        }

        // Decode the payload
        $data = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_REST_Response(
                ['success' => false, 'message' => 'Invalid JSON payload.'],
                400
            );
        }

        // Process the webhook event
        $event = $request->get_header('X-GitHub-Event');
        if ($event === 'push') {
            // Example: Handle push events
            $this->processPushEvent($data);
        } elseif ($event === 'release') {
            // Example: Handle release events
            $this->processReleaseEvent($data);
        } else {
            return new WP_REST_Response(
                ['success' => false, 'message' => 'Unhandled event type.'],
                400
            );
        }

        // Ensure the release event deletes update transients
        if ($event === 'release') {
            if (isset($data['action']) && $data['action'] === 'published') {
                delete_site_transient('update_plugins');
                delete_site_transient('update_themes');
            }
        }

        return new WP_REST_Response(
            ['success' => true, 'message' => 'Webhook processed successfully.'],
            200
        );
    }

    private function processPushEvent(array $data): void
    {
        // Logic for processing push events
    }

    private function processReleaseEvent(array $data): void
    {
        // Logic for processing release events
    }
}
