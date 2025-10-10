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

        // Validate private key format
        if (!$this->is_valid_private_key($private_key)) {
            return new WP_REST_Response(['message' => esc_html__('Invalid private key format.', 'wp2-update')], 400);
        }

        // Additional validation for app_id and installation_id
        if ($app_id <= 0) {
            return new WP_REST_Response(['message' => esc_html__('Invalid App ID.', 'wp2-update')], 400);
        }

        if ($installation_id <= 0) {
            return new WP_REST_Response(['message' => esc_html__('Invalid Installation ID.', 'wp2-update')], 400);
        }

        // Validate webhook secret
        if (empty($webhook_secret) || strlen($webhook_secret) < 10) {
            return new WP_REST_Response(['message' => esc_html__('Invalid webhook secret. It must be at least 10 characters long.', 'wp2-update')], 400);
        }

        // Validate the new encryption key
        if (empty($encryption_key) || strlen($encryption_key) < 16) {
            return new WP_REST_Response(['message' => esc_html__('Invalid Encryption Key. It must be at least 16 characters long.', 'wp2-update')], 400);
        }

        $this->credentialService->store_app_credentials([
            'name'            => $app_name,
            'app_id'          => $app_id,
            'installation_id' => $installation_id,
            'private_key'     => $private_key,
            'webhook_secret'  => $webhook_secret,
            'encryption_key'  => $encryption_key,
        ]);

        do_action('wp2_update_credentials_saved', $app_id, $installation_id);

        return new WP_REST_Response(['message' => esc_html__('Credentials saved successfully.', 'wp2-update')], 200);
    }

    public function rest_disconnect(WP_REST_Request $request): WP_REST_Response {
        $this->credentialService->clear_credentials();

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
        // Example validation: Ensure the key starts and ends with expected markers
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

        // Add the state nonce directly to the callback URL as a query parameter.
        $callback_url = esc_url_raw(add_query_arg('state', $state, admin_url('admin.php?page=wp2-update-github-callback')));

        // Temporarily store the encryption key, keyed by the state for security.
        set_transient('wp2_ek_' . $state, $encryption_key, 10 * MINUTE_IN_SECONDS);

        $manifest = json_decode($raw_manifest, true) ?: [];
        $manifest['name'] = sanitize_text_field($request->get_param('name') ?: (get_bloginfo('name') . ' Updater'));
        $manifest['url'] = esc_url_raw(home_url());
        $manifest['redirect_url'] = $callback_url;
        $manifest['hook_attributes'] = [
            'url'    => esc_url_raw(rest_url('wp2-update/v1/webhook')),
            'active' => true,
        ];

        $manifest_json = wp_json_encode($manifest, JSON_UNESCAPED_SLASHES);

        return new WP_REST_Response(
            [
                'manifest' => $manifest_json,
                'account'  => $org_name ? "https://github.com/organizations/{$org_name}/settings/apps/new" : 'https://github.com/settings/apps/new',
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

        $response = wp_remote_post(
            "https://api.github.com/app-manifests/{$code}/conversions",
            [
                'headers' => [
                    'Accept' => 'application/vnd.github+json',
                ],
            ]
        );
        if (is_wp_error($response)) {
            \WP2\Update\Utils\Logger::log('ERROR', 'GitHub OAuth exchange failed: ' . $response->get_error_message());
            return new WP_REST_Response(['message' => esc_html($response->get_error_message())], 500);
        }

        $body = wp_remote_retrieve_body($response);
        $credentials = json_decode($body, true);

        if (!is_array($credentials) || empty($credentials['id']) || empty($credentials['pem'])) {
            \WP2\Update\Utils\Logger::log('ERROR', 'GitHub OAuth exchange failed: unexpected payload.');
            return new WP_REST_Response(['message' => esc_html__('Unexpected response from GitHub.', 'wp2-update')], 500);
        }

        // Retrieve the encryption key from the transient.
        $transient_key = 'wp2_ek_' . $state;
        $encryption_key = get_transient($transient_key);
        delete_transient($transient_key); // Clean up the transient immediately.

        if (empty($encryption_key)) {
            return new WP_REST_Response(['message' => esc_html__('Your session has expired. Please try the connection process again.', 'wp2-update')], 400);
        }

        $this->credentialService->store_app_credentials([
            'name'            => $credentials['name'] ?? ($credentials['slug'] ?? ''),
            'app_id'          => absint($credentials['id']),
            'installation_id' => absint($credentials['installation_id'] ?? 0),
            'private_key'     => (string) $credentials['pem'],
            'webhook_secret'  => (string) ($credentials['webhook_secret'] ?? ''),
        ]);

        do_action('wp2_update_credentials_saved', $credentials['id'], $credentials['installation_id'] ?? 0);

        return new WP_REST_Response([
            'success' => true,
            'app_id'  => absint($credentials['id']),
        ], 200);
    }
}
