<?php

namespace WP2\Update\REST\Controllers;

use WP2\Update\Core\API\CredentialService;
use WP2\Update\Security\Permissions;
use WP_REST_Request;
use WP_REST_Response;

final class CredentialsController {
    private CredentialService $credentialService;

    public function __construct(CredentialService $credentialService) {
        $this->credentialService = $credentialService;
    }

    public static function check_permissions(WP_REST_Request $request): bool {
        return Permissions::current_user_can_manage($request);
    }

    public function rest_save_credentials(WP_REST_Request $request): WP_REST_Response {
        $app_name        = sanitize_text_field($request->get_param('wp2_app_name'));
        $app_id          = absint($request->get_param('wp2_app_id'));
        $installation_id = absint($request->get_param('wp2_installation_id'));
        $private_key     = wp_unslash($request->get_param('wp2_private_key'));
        $webhook_secret  = sanitize_text_field($request->get_param('wp2_webhook_secret'));
        $encryption_key  = sanitize_text_field($request->get_param('encryption_key'));
        $app_slug        = sanitize_title($request->get_param('wp2_app_slug'));
        $app_html_url    = esc_url_raw($request->get_param('wp2_app_html_url'));
        $app_uid         = sanitize_text_field($request->get_param('app_uid'));
        $account_type    = $request->get_param('account_type');
        $organization    = $request->get_param('organization');

        // Validate private key format
        if (!$this->is_valid_private_key($private_key)) {
            return new WP_REST_Response(['message' => esc_html__('Invalid private key format.', 'wp2-update')], 400);
        }

        // Additional validation for app_id and installation_id
        if ($app_id <= 0) {
            return new WP_REST_Response(['message' => esc_html__('Invalid App ID.', 'wp2-update')], 400);
        }

        // Validate webhook secret
        if (empty($webhook_secret) || strlen($webhook_secret) < 10) {
            return new WP_REST_Response(['message' => esc_html__('Invalid webhook secret. It must be at least 10 characters long.', 'wp2-update')], 400);
        }

        // Validate the new encryption key
        if (empty($encryption_key) || strlen($encryption_key) < 16) {
            return new WP_REST_Response(['message' => esc_html__('Invalid Encryption Key. It must be at least 16 characters long.', 'wp2-update')], 400);
        }

        $app = $this->credentialService->store_app_credentials([
            'app_uid'        => $app_uid,
            'name'           => $app_name,
            'app_id'         => $app_id,
            'installation_id'=> $installation_id,
            'slug'           => $app_slug,
            'html_url'       => $app_html_url,
            'account_type'   => is_string($account_type) ? $account_type : 'user',
            'organization'   => is_string($organization) ? $organization : '',
            'private_key'    => $private_key,
            'webhook_secret' => $webhook_secret,
            'encryption_key' => $encryption_key,
        ]);

        // Updated hook to include app ID
        do_action('wp2_update_credentials_saved', $app_id, $installation_id, $app['id']);

        return new WP_REST_Response([
            'app' => $app,
            'requires_installation' => 0 === $installation_id,
        ], 200);
    }

    public function rest_disconnect(WP_REST_Request $request): WP_REST_Response {
        $appId = $request->get_param('app_id');
        if (empty($appId)) {
            return new WP_REST_Response(['message' => esc_html__('Missing app identifier.', 'wp2-update')], 400);
        }

        $this->credentialService->clear_stored_credentials((string) $appId);

        do_action('wp2_update_credentials_disconnected');

        return new WP_REST_Response(['message' => esc_html__('Disconnected successfully.', 'wp2-update')], 200);
    }

    /**
     * Validates the format of a private key.
     *
     * @param string $key The private key to validate.
     * @return bool True if the key is valid, false otherwise.
     */
    private function is_valid_private_key(string $key): bool {
        return preg_match('/-----BEGIN (.*) PRIVATE KEY-----.*-----END (.*) PRIVATE KEY-----/s', $key) === 1;
    }

    public function rest_get_connect_url(WP_REST_Request $request): WP_REST_Response {
        $account_type = $request->get_param('account_type') === 'organization' ? 'organization' : 'user';
        $org_name     = '';
        if ('organization' === $account_type) {
            $org_name = sanitize_title($request->get_param('organization'));
            if ('' === $org_name) {
                return new WP_REST_Response(['message' => esc_html__('An organization slug is required for organization apps.', 'wp2-update')], 400);
            }
        }
        $raw_manifest = $request->get_param('manifest');
        if (!is_string($raw_manifest) || $raw_manifest === '') {
            \WP2\Update\Utils\Logger::log('ERROR', 'Manifest parameter is missing or invalid.');
            return new WP_REST_Response(['message' => esc_html__('Manifest payload is required.', 'wp2-update')], 400);
        }
        $encryption_key = sanitize_text_field($request->get_param('encryption_key'));
        if (empty($encryption_key) || strlen($encryption_key) < 16) {
            return new WP_REST_Response(['message' => esc_html__('A valid encryption key is required.', 'wp2-update')], 400);
        }

        // Create the security nonce (state).
        $state = wp_create_nonce('wp2-manifest');
        $this->store_encryption_key_for_state($state, $encryption_key);

        $callback_url = esc_url_raw(admin_url('admin.php?page=wp2-update-github-callback'));

        // Validate the generated callback URL
        if (!filter_var($callback_url, FILTER_VALIDATE_URL)) {
            \WP2\Update\Utils\Logger::log('ERROR', 'Invalid callback URL: ' . $callback_url);
            return new WP_REST_Response(['message' => esc_html__('Invalid callback URL.', 'wp2-update')], 400);
        }

        $manifest = json_decode($raw_manifest, true) ?: [];
        $manifest['name'] = sanitize_text_field($request->get_param('name') ?: (get_bloginfo('name') . ' Updater'));
        $manifest['url'] = esc_url_raw(home_url());
        $manifest['hook_attributes'] = [
            'url'    => esc_url_raw(rest_url('wp2-update/v1/webhook')),
            'active' => true,
        ];

        // Ensure the setup_url is correctly set for the GitHub App manifest
        $manifest['setup_url'] = esc_url_raw(admin_url('admin.php?page=wp2-update'));

        // Remove polling URL as it is not a permitted key in the GitHub App manifest
        unset($manifest['polling_url']);

        $manifest_json = wp_json_encode($manifest, JSON_UNESCAPED_SLASHES);

        return new WP_REST_Response(
            [
                'manifest' => $manifest_json,
                'account'  => $org_name ? "https://github.com/organizations/{$org_name}/settings/apps/new" : 'https://github.com/settings/apps/new',
                'state'    => $state,
                'account_type' => $account_type,
                'organization' => $org_name,
            ],
            200
        );
    }

    /**
     * Exchanges the authorization code for app credentials and stores them.
     *
     * This method retrieves the authorization code from the request, exchanges it
     * for app credentials using the GitHub API, and stores the credentials using
     * the CredentialService.
     *
     * @param WP_REST_Request $request The REST request object containing the authorization code.
     * @return WP_REST_Response The REST response object indicating success or failure.
     */
    public function rest_exchange_code(WP_REST_Request $request): WP_REST_Response {
        $state = (string) $request->get_param('state');
        if ('' === $state || !wp_verify_nonce($state, 'wp2-manifest')) {
            return new WP_REST_Response(['message' => esc_html__('Invalid state parameter.', 'wp2-update')], 400);
        }

        $code = sanitize_text_field($request->get_param('code'));
        if (!$code) {
            return new WP_REST_Response(['message' => esc_html__('Authorization code is missing.', 'wp2-update')], 400);
        }

        $encryption_payload = $this->get_encryption_payload_for_state($state);
        if (!$encryption_payload) {
            return new WP_REST_Response(['message' => esc_html__('Encryption key could not be retrieved. Please restart the connection.', 'wp2-update')], 400);
        }
        $encryption_key         = $encryption_payload['key'];
        $encryption_transient   = $encryption_payload['transient'];

        $url = "https://api.github.com/app-manifests/{$code}/conversions";
        $headers = [
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'WP2-Update-Plugin',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];

        $response = wp_remote_post($url, [
            'headers'     => $headers,
            'timeout'     => 20,
            'body'        => '',
            'httpversion' => '1.1',
        ]);

        if (is_wp_error($response)) {
            \WP2\Update\Utils\Logger::log('ERROR', 'GitHub OAuth exchange failed: ' . $response->get_error_message());
            return new WP_REST_Response(['message' => esc_html($response->get_error_message())], 500);
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body       = wp_remote_retrieve_body($response);

        if ($statusCode < 200 || $statusCode >= 300) {
            \WP2\Update\Utils\Cache::delete($encryption_transient);
            \WP2\Update\Utils\Logger::log(
                'ERROR',
                sprintf(
                    'GitHub OAuth exchange HTTP %d. Response: %s',
                    $statusCode,
                    is_string($body) && $body !== '' ? $body : '[empty body]'
                )
            );

            $message = __('Unexpected response from GitHub.', 'wp2-update');
            if ($statusCode === 404 || $statusCode === 410) {
                $message = __('The GitHub manifest code has expired. Please restart the connection process.', 'wp2-update');
            }

            return new WP_REST_Response(['message' => $message], $statusCode >= 400 && $statusCode < 500 ? 400 : 500);
        }

        $credentials = json_decode($body, true);

        if (empty($credentials['id']) || empty($credentials['pem']) || empty($credentials['html_url'])) {
            \WP2\Update\Utils\Cache::delete($encryption_transient);
            \WP2\Update\Utils\Logger::log(
                'ERROR',
                'GitHub OAuth exchange returned invalid data: ' . (is_string($body) && $body !== '' ? $body : '[empty body]')
            );
            return new WP_REST_Response(['message' => __('Unexpected response from GitHub.', 'wp2-update')], 500);
        }

        // The installation_id is now included in the manifest conversion response.
        $installation_id = isset($credentials['installation_id']) ? absint($credentials['installation_id']) : 0;
        if (0 === $installation_id) {
            \WP2\Update\Utils\Logger::log('INFO', 'GitHub did not return an installation ID during manifest conversion. The user must install the app manually.');
        }

        // Pass sensitive data directly to store_app_credentials for encryption
        $app = $this->credentialService->store_app_credentials([
            'name'            => $credentials['name'] ?? '',
            'app_id'          => absint($credentials['id']),
            'installation_id' => absint($installation_id),
            'slug'            => sanitize_title($credentials['slug'] ?? ''),
            'html_url'        => esc_url_raw($credentials['html_url'] ?? ''),
            'private_key'     => (string) $credentials['pem'],
            'webhook_secret'  => (string) ($credentials['webhook_secret'] ?? ''),
            'encryption_key'  => $encryption_key,
        ]);

        // Updated hook to include app ID consistently
        do_action('wp2_update_credentials_saved', $credentials['id'], $installation_id, $app['id']);
        \WP2\Update\Utils\Cache::delete($encryption_transient);

        return new WP_REST_Response([
            'success' => true,
            'app'     => $app,
            'app_id'  => absint($credentials['id']),
            'requires_installation' => 0 === $installation_id,
        ], 200);
    }

    /**
     * Assigns a repository to a GitHub App installation.
     *
     * @param WP_REST_Request $request The REST request object containing installation_id and repository_id.
     * @return WP_REST_Response The REST response object indicating success or failure.
     */
    public function assign_repository(WP_REST_Request $request): WP_REST_Response
    {
        $installationId = absint($request->get_param('installation_id'));
        $repositoryId = absint($request->get_param('repository_id'));
        $appUid = $request->get_param('app_uid');

        if (!$installationId || !$repositoryId) {
            return new WP_REST_Response(['message' => __('Invalid installation or repository ID.', 'wp2-update')], 400);
        }

        $token = $this->credentialService->get_installation_token($installationId, $appUid ? (string) $appUid : null);
        if (!$token) {
            return new WP_REST_Response(['message' => __('Failed to retrieve installation token.', 'wp2-update')], 403);
        }

        $response = wp_remote_request(
            "https://api.github.com/user/installations/{$installationId}/repositories/{$repositoryId}",
            [
                'method'  => 'PUT',
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept'        => 'application/vnd.github+json',
                ],
            ]
        );

        if (is_wp_error($response)) {
            \WP2\Update\Utils\Logger::log('ERROR', 'Failed to assign repository: ' . $response->get_error_message());
            return new WP_REST_Response(['message' => __('Failed to assign repository.', 'wp2-update')], 500);
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status === 204) {
            return new WP_REST_Response(['message' => __('Repository successfully assigned.', 'wp2-update')], 200);
        }

        $body = wp_remote_retrieve_body($response);
        \WP2\Update\Utils\Logger::log('ERROR', 'GitHub API error: ' . $body);
        return new WP_REST_Response(['message' => __('Failed to assign repository.', 'wp2-update')], $status);
    }

    private function format_response(array $data, int $status = 200): WP_REST_Response {
        return new WP_REST_Response([
            'success' => $status >= 200 && $status < 300,
            'data'    => $data,
        ], $status);
    }

    private function store_encryption_key_for_state(string $state, string $encryptionKey): void {
        $transientKey = $this->build_encryption_transient_key($state);
        \WP2\Update\Utils\Cache::set(
            $transientKey,
            [
                'key'  => $encryptionKey,
                'user' => get_current_user_id(),
            ],
            10 * MINUTE_IN_SECONDS
        );
    }

    private function get_encryption_payload_for_state(string $state): ?array {
        $transientKey = $this->build_encryption_transient_key($state);
        $payload = \WP2\Update\Utils\Cache::get($transientKey);

        if (!is_array($payload) || empty($payload['key'])) {
            return null;
        }

        $storedUser = isset($payload['user']) ? (int) $payload['user'] : 0;
        $currentUser = get_current_user_id();
        if ($storedUser > 0 && $currentUser > 0 && $storedUser !== $currentUser) {
            return null;
        }

        return [
            'key'       => (string) $payload['key'],
            'transient' => $transientKey,
        ];
    }

    private function build_encryption_transient_key(string $state): string {
        $userId = get_current_user_id();
        return 'wp2_enc_' . md5($state . '|' . $userId);
    }

    public function start_oauth_flow(WP_REST_Request $request): WP_REST_Response {
        session_start();

        $encryption_key = bin2hex(random_bytes(16)); // Generate a secure encryption key
        $_SESSION['encryption_key'] = $encryption_key; // Store it in the session

        $redirect_url = 'https://github.com/login/oauth/authorize';
        $query_params = [
            'client_id' => 'your-client-id',
            'redirect_uri' => admin_url('admin.php?page=wp2-update-github-callback'),
            'state' => $encryption_key,
        ];

        return new WP_REST_Response(['redirect_url' => $redirect_url . '?' . http_build_query($query_params)], 200);
    }

    public function handle_oauth_callback(WP_REST_Request $request): WP_REST_Response {
        session_start();

        $encryption_key = $_SESSION['encryption_key'] ?? null;
        unset($_SESSION['encryption_key']); // Remove it from the session after use

        if (!$encryption_key) {
            return new WP_REST_Response(['message' => esc_html__('Encryption key not found in session.', 'wp2-update')], 400);
        }

        return new WP_REST_Response(['message' => esc_html__('OAuth callback handled successfully.', 'wp2-update')], 200);
    }
}
