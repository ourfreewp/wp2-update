<?php

namespace WP2\Update\Admin;

use WP2\Update\Core\API\GitHubApp\Init as GitHubApp;
use WP2\Update\Core\API\Service as GitHubService;
use WP2\Update\Core\Updates\PackageFinder;
use WP2\Update\Utils\SharedUtils;
use WP_REST_Request;
use WP_REST_Response;
use Exception;

/**
 * Registers the simplified admin UI and handles form submissions.
 */
class Init
{
    private GitHubService $githubService;
    private PackageFinder $packages;
    private Pages $pages;
    private GitHubApp $githubApp;
    private SharedUtils $utils; // Add this property to the class

    /**
     * Constructor for the Init class.
     *
     * @param GitHubService $githubService Service for interacting with GitHub.
     * @param PackageFinder $packages Service for finding and managing packages.
     * @param SharedUtils $utils Utility class for shared functionality.
     * @param GitHubApp $githubApp Service for managing GitHub App credentials.
     */
    public function __construct(GitHubService $githubService, PackageFinder $packages, SharedUtils $utils, GitHubApp $githubApp)
    {
        $this->githubService = $githubService;
        $this->packages      = $packages;
        $this->githubApp     = $githubApp;
        $this->utils         = $utils; // Initialize the utils property
        $this->pages         = new Pages($githubService, $packages, $githubApp);
    }

    /**
     * Register admin-facing hooks.
     *
     * This method adds actions for the admin menu, form submissions, and script enqueuing.
     */
    public function register_hooks(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_wp2_save_github_app', [$this, 'handle_save_credentials']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        $this->register_debug_hooks(); // Register debug hooks
        $this->register_github_callback_page(); // Register GitHub callback page
    }

    /**
     * Adds the single plugin settings page.
     *
     * This method registers a top-level admin menu page for managing WP2 Updates.
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
     *
     * This method handles the `admin_post_wp2_save_github_app` action to save
     * GitHub App credentials securely in the database.
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
        $webhookSecret  = isset($_POST['wp2_webhook_secret']) ? sanitize_text_field(wp_unslash($_POST['wp2_webhook_secret'])) : '';

        $this->githubService->store_app_credentials([
            'name'            => $appName,
            'app_id'          => $appId,
            'installation_id' => $installationId,
            'private_key'     => $privateKey,
        ]);

        if (!empty($webhookSecret)) {
            update_option('wp2_webhook_secret', wp_hash_password($webhookSecret));
        }

        // Action hook to trigger events after saving credentials
        do_action('wp2_update_credentials_saved', $appId, $installationId);

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
     *
     * This method defines the following routes:
     * - `/connection-status` (GET): Checks the connection status of the GitHub App.
     * - `/run-update-check` (POST): Triggers an update check for packages.
     * - `/test-connection` (POST): Tests the GitHub App connection.
     * - `/validate-connection` (POST): Validates the GitHub App connection.
     */
    public function register_rest_routes(): void
    {
        error_log('[DEBUG] register_rest_routes executed.');

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

        register_rest_route('wp2-update/v1', '/validate-connection', [
            'methods'             => 'POST',
            'callback'            => [$this, 'validate_connection'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        // Log registration for debugging
        error_log('[WP2 Update] REST API endpoint /validate-connection registered successfully.');

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

        register_rest_route('wp2-update/v1', '/disconnect', [
            'methods'             => 'POST',
            'callback'            => [$this, 'rest_disconnect'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        // Debug log for route registration
        error_log('[WP2 Update] REST API endpoint /disconnect registered successfully.');

        register_rest_route('wp2-update/v1', '/github/connect-url', [
            'methods'             => 'GET',
            'callback'            => [$this, 'rest_get_connect_url'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        register_rest_route('wp2-update/v1', '/github/exchange-code', [
            'methods'             => 'POST',
            'callback'            => [$this, 'rest_exchange_code'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        global $wp_rest_server;
        if (isset($wp_rest_server)) {
            do_action('qm/debug', '[DEBUG] Registered REST Routes: ' . print_r($wp_rest_server->get_routes(), true));
        }
    }

    /**
     * Checks if the current user has the required permissions.
     *
     * This method verifies that the user has the `manage_options` capability
     * and that the provided nonce is valid.
     *
     * @return bool True if the user has the required permissions, false otherwise.
     */
    public function check_permissions(): bool
    {
        $nonce = $_REQUEST['wp2_update_nonce'] ?? '';
        return current_user_can('manage_options') && wp_verify_nonce($nonce, 'wp2_update_nonce');
    }

    /**
     * Handles the connection status retrieval.
     *
     * This method is used as a callback for the `/connection-status` REST API route.
     * It retrieves the current connection status of the GitHub App.
     *
     * @param WP_REST_Request $request The REST API request object.
     * @return WP_REST_Response The response containing the connection status.
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
     * Fetches all repositories and their latest releases.
     */
    public function sync_packages(WP_REST_Request $request): WP_REST_Response
    {
        try {
            // Fetch managed plugins and themes
            $managedPlugins = $this->packages->get_managed_plugins();
            $managedThemes = $this->packages->get_managed_themes();

            // Create a lookup map for installed versions
            $installedPackages = array_merge($managedPlugins, $managedThemes);
            $installedMap = [];
            foreach ($installedPackages as $package) {
                $installedMap[$package['repo']] = $package['version'];
            }

            // Fetch repositories from GitHub
            $githubRepos = $this->githubService->getUserRepositories();

            // Merge GitHub data with installed packages
            $mergedData = [];
            foreach ($githubRepos as $repo) {
                $repoSlug = $repo['full_name'];
                $releases = $this->githubService->getAllReleases($repo['owner']['login'], $repo['name']);
                $mergedData[] = [
                    'repo' => $repoSlug,
                    'name' => $repo['name'] ?? '',
                    'description' => $repo['description'] ?? '',
                    'latest_version' => $releases[0]['tag_name'] ?? null,
                    'installed_version' => $installedMap[$repoSlug] ?? null,
                    'releases' => $releases,
                    'status' => $installedMap[$repoSlug] === ($releases[0]['tag_name'] ?? null) ? 'up-to-date' : 'outdated',
                ];
            }

            return new WP_REST_Response($mergedData, 200);
        } catch (Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Triggers the update for a specific package.
     */
    public function manage_packages(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $repoSlug = $request->get_param('repo_slug');
            $version = $request->get_param('version');

            if (!$repoSlug || !$version) {
                return new WP_REST_Response(['error' => 'Missing required parameters.'], 400);
            }

            // Fetch the release for the requested version
            $release = $this->githubService->get_release_by_version($repoSlug, $version);
            if (!$release) {
                return new WP_REST_Response(['error' => 'Release not found.'], 404);
            }

            // Get the download URL for the release asset
            $downloadUrl = $this->utils->get_zip_url_from_release($release);
            if (!$downloadUrl) {
                return new WP_REST_Response(['error' => 'Download URL not found.'], 404);
            }

            // Download the release asset to a temporary file
            $tempFile = $this->githubService->download_to_temp_file($downloadUrl);
            if (!$tempFile) {
                return new WP_REST_Response(['error' => 'Failed to download the release asset.'], 500);
            }

            // Ensure the correct upgrader is used based on the package type
            $upgrader = strpos($repoSlug, '/themes/') !== false ? new \Theme_Upgrader() : new \Plugin_Upgrader();

            // Install the update
            $result = $upgrader->install($tempFile);

            if (is_wp_error($result)) {
                return new WP_REST_Response(['error' => $result->get_error_message()], 500);
            }

            return new WP_REST_Response(['success' => true, 'message' => 'Package updated successfully.'], 200);
        } catch (Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Helper method to log errors and display admin notices.
     *
     * @param string $message The message to log and display.
     */
    private function log_and_notify(string $message): void
    {
        error_log($message);
        add_action('admin_notices', function() use ($message) {
            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        });
    }

    /**
     * Localize script data for admin pages.
     */
    private function localize_admin_script(): void
    {
        wp_localize_script(
            'wp2-update-admin', // Handle of the script to localize
            'wp2UpdateData', // Object name in JavaScript
            [
                'initialState' => $this->get_initial_state(), // Pass initial state data
                'nonce' => wp_create_nonce('wp2_update_nonce'), // Generate a nonce for security
            ]
        );

        // Localize wpApiSettings for admin-main.js
        wp_localize_script(
            'wp2-update-admin-main',
            'wpApiSettings',
            [
                'root' => esc_url_raw(rest_url()),
                'nonce' => wp_create_nonce('wp_rest')
            ]
        );

        // Debug log to confirm localization
        do_action('qm/debug', '[DEBUG] Localized wpApiSettings for wp2-update-admin-main.');
    }

    /**
     * Enqueue admin scripts and styles using Vite loader.
     */
    public function enqueue_admin_scripts(): void
    {
        $manifest_path = WP2_UPDATE_PLUGIN_DIR . 'dist/.vite/manifest.json';

        if (!file_exists($manifest_path)) {
            do_action('qm/debug', '[ERROR] Manifest file not found: ' . $manifest_path);
            return;
        }

        $manifest = json_decode(file_get_contents($manifest_path), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            do_action('qm/debug', '[ERROR] Failed to decode Vite manifest JSON: ' . json_last_error_msg());
            return;
        }

        // Enqueue admin script
        if (isset($manifest['assets/scripts/admin-main.js'])) {
            $script_path = $manifest['assets/scripts/admin-main.js']['file'];
            $script_url = WP2_UPDATE_PLUGIN_URL . 'dist/' . $script_path;

            wp_enqueue_script(
                'wp2-update-admin-main',
                $script_url,
                ['wp-api-fetch'],
                null,
                true
            );

            wp_localize_script(
                'wp2-update-admin-main',
                'wpApiSettings',
                [
                    'root' => esc_url_raw(rest_url()),
                    'nonce' => wp_create_nonce('wp_rest')
                ]
            );

            do_action('qm/debug', '[DEBUG] Enqueued admin-main.js with URL: ' . $script_url);
        }

        // Enqueue admin style
        if (isset($manifest['assets/styles/admin-main.scss'])) {
            $style_path = $manifest['assets/styles/admin-main.scss']['file'];
            $style_url = WP2_UPDATE_PLUGIN_URL . 'dist/' . $style_path;

            wp_enqueue_style(
                'wp2-update-admin-main',
                $style_url,
                [],
                null
            );

            do_action('qm/debug', '[DEBUG] Enqueued admin-main.css with URL: ' . $style_url);
        }
    }

    /**
     * Handles incoming webhook requests with enhanced validation.
     */
    public function handle_webhook(WP_REST_Request $request): WP_REST_Response
    {
        $signature = $request->get_header('X-Hub-Signature-256');

        try {
            $payload = $request->get_body();

            // Validate the signature
            $credentials = $this->githubService->get_stored_credentials();
            $secret = $credentials['webhook_secret'] ?? ''; // Use the webhook secret instead of the private key
            $calculatedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

            if (!$signature || !hash_equals($calculatedSignature, $signature)) {
                error_log('Webhook validation failed: Invalid or missing signature.');
                return new WP_REST_Response(
                    ['success' => false, 'message' => 'Invalid or missing signature.'],
                    403
                );
            }

            // Decode the payload
            $data = json_decode($payload, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('Webhook payload decoding failed: ' . json_last_error_msg());
                return new WP_REST_Response(
                    ['success' => false, 'message' => 'Invalid JSON payload.'],
                    400
                );
            }

            // Validate the User-Agent header
            $userAgent = $request->get_header('User-Agent');
            if (strpos($userAgent, 'GitHub-Hookshot') === false) {
                error_log('Webhook validation failed: Invalid User-Agent.');
                return new WP_REST_Response(
                    ['success' => false, 'message' => 'Invalid User-Agent.'],
                    403
                );
            }

            // Retrieve the stored webhook secret
            $storedSecret = get_option('wp2_webhook_secret');
            if (!$storedSecret || !wp_check_password($secret, $storedSecret)) {
                error_log('Webhook validation failed: Secret mismatch.');
                return new WP_REST_Response(
                    ['success' => false, 'message' => 'Secret mismatch.'],
                    403
                );
            }

            // Process the webhook event
            $event = $request->get_header('X-GitHub-Event');
            if ($event === 'push') {
                $this->processPushEvent($data);
            } elseif ($event === 'release') {
                $this->processReleaseEvent($data);
            } else {
                error_log('Unhandled webhook event type: ' . $event);
                return new WP_REST_Response(
                    ['success' => false, 'message' => 'Unhandled event type.'],
                    400
                );
            }

            // Ensure the release event deletes update transients
            if ($event === 'release') {
                if (isset($data['action']) && in_array($data['action'], ['published', 'deleted'], true)) {
                    delete_site_transient('update_plugins');
                    delete_site_transient('update_themes');
                }
            }
        } catch (Exception $e) {
            error_log('Unexpected error in webhook handler: ' . $e->getMessage());
            return new WP_REST_Response(
                ['success' => false, 'message' => 'An unexpected error occurred while processing the webhook.'],
                500
            );
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
        // Clear update transients
        delete_site_transient('update_plugins');
        delete_site_transient('update_themes');

        // Log the release event
        error_log('Processed release event: ' . json_encode($data));
    }

    /**
     * Retrieves the initial state for the JavaScript application.
     *
     * @return array The initial state data.
     */
    private function get_initial_state(): array
    {
        $credentials = $this->githubService->get_stored_credentials();

        return [
            'isConnected' => !empty($credentials),
            'connection' => [
                'appName' => $credentials['name'] ?? '',
                'appId' => $credentials['app_id'] ?? '',
                'installationId' => $credentials['installation_id'] ?? '',
            ],
        ];
    }

    /**
     * Handles the REST API request to disconnect.
     *
     * @param WP_REST_Request $request The REST API request object.
     * @return WP_REST_Response The response object.
     */
    public function rest_disconnect(WP_REST_Request $request): WP_REST_Response
    {
        error_log('[DEBUG] Executing rest_disconnect method.');

        try {
            // Clear stored credentials
            $this->githubService->clear_stored_credentials();

            // Log success
            error_log('[DEBUG] Successfully cleared stored credentials.');

            // Return a success response
            return new WP_REST_Response([
                'success' => true,
                'message' => 'Disconnected successfully.',
            ], 200);
        } catch (Exception $e) {
            // Log the error
            error_log('[ERROR] rest_disconnect failed: ' . $e->getMessage());

            // Return an error response
            return new WP_REST_Response([
                'success' => false,
                'message' => 'An error occurred while disconnecting.',
            ], 500);
        }
    }

    /**
     * Display a debug panel in the admin area.
     *
     * This method hooks into `admin_notices` to display debugging information.
     */
    public function display_debug_panel(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $current_user = wp_get_current_user();
        $debug_data = [
            'wp2UpdateData' => isset($GLOBALS['wp2UpdateData']) ? 'Defined' : 'Not defined',
            'Current User' => [
                'ID' => $current_user->ID,
                'user_login' => $current_user->user_login,
            ],
            'Admin URL' => admin_url(),
            'AJAX URL' => admin_url('admin-ajax.php'),
        ];

        echo '<div class="notice notice-info is-dismissible">';
        echo '<h2>WP2 Update Debug Panel</h2>';
        echo '<pre>' . esc_html(print_r($debug_data, true)) . '</pre>';
        echo '</div>';
    }

    /**
     * Register hooks for the debug panel.
     */
    public function register_debug_hooks(): void
    {
        add_action('admin_notices', [$this, 'display_debug_panel']);
    }

    /**
     * Generates the GitHub App manifest and redirect URL.
     */
    public function rest_get_connect_url(WP_REST_Request $request): WP_REST_Response
    {
        $orgName = $request->get_param('organization');

        $manifest = [
            'name' => $request->get_param('name') ?: get_bloginfo('name') . ' Updater',
            'url' => home_url(),
            'redirect_url' => admin_url('tools.php?page=wp2-update-callback'), // A dedicated callback page
            'callback_urls' => [ home_url() ],
            'public' => false,
            'default_permissions' => [
                'contents' => 'read',
                'metadata' => 'read',
            ],
            'default_events' => ['release'],
        ];

        $url = 'https://github.com/settings/apps/new?manifest=' . rawurlencode(json_encode($manifest));
        if ($orgName) {
            $url .= '&organization_name=' . rawurlencode($orgName);
        }

        return new WP_REST_Response(['url' => $url]);
    }

    /**
     * Exchanges the temporary code from GitHub for permanent credentials.
     */
    public function rest_exchange_code(WP_REST_Request $request): WP_REST_Response
    {
        $code = $request->get_param('code');
        if (!$code) {
            return new WP_REST_Response(['message' => 'Authorization code is missing.'], 400);
        }

        // This is the official GitHub endpoint for the manifest flow
        $response = wp_remote_post("https://api.github.com/app-manifests/{$code}/conversions");

        if (is_wp_error($response)) {
            return new WP_REST_Response(['message' => $response->get_error_message()], 500);
        }

        $body = wp_remote_retrieve_body($response);
        $credentials = json_decode($body, true);

        // Use your existing secure storage method
        $this->githubService->store_app_credentials($credentials);

        return new WP_REST_Response(['success' => true]);
    }

    /**
     * Register a hidden admin page for the GitHub callback.
     *
     * This method adds a submenu page under the tools menu for handling GitHub
     * callback requests. The page is hidden from the admin menu.
     */
    public function register_github_callback_page(): void
    {
        add_submenu_page(
            null, // Hidden page, no parent slug
            'GitHub Callback', // Page title
            'GitHub Callback', // Menu title
            'manage_options', // Capability
            'wp2-update-github-callback', // Menu slug
            [$this, 'render_github_callback_page'] // Callback function
        );
    }

    /**
     * Renders the GitHub callback page.
     *
     * This method is called when the hidden submenu page is accessed. It
     * simply returns a 200 OK response with a message.
     */
    public function render_github_callback_page(): void
    {
        // Enqueue the GitHub callback script
        wp_enqueue_script(
            'wp2-update-github-callback',
            WP2_UPDATE_PLUGIN_URL . 'assets/scripts/github-callback.js',
            [],
            '1.0.0',
            true
        );

        // Localize script data for GitHub callback page
        wp_localize_script(
            'wp2-update-github-callback',
            'wp2UpdateData',
            [
                'nonce' => wp_create_nonce('wp2_update_nonce')
            ]
        );

        // Output a minimal HTML structure
        echo '<div id="wp2-update-github-callback"></div>';
    }
}
