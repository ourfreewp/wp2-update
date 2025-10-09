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

        $this->credentialService->store_app_credentials([
            'name'            => $app_name,
            'app_id'          => $app_id,
            'installation_id' => $installation_id,
            'private_key'     => $private_key,
            'webhook_secret'  => $webhook_secret, // Pass it here
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
                return new WP_REST_Response([
                    'message' => esc_html__('An organization slug is required for organization apps.', 'wp2-update'),
                ], 400);
            }
        }

        $raw_manifest = $request->get_param('manifest');
        $decoded_manifest = [];
        if (is_string($raw_manifest) && $raw_manifest !== '') {
            $decoded_manifest = json_decode($raw_manifest, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded_manifest)) {
                return new WP_REST_Response([
                    'message' => esc_html__('Invalid manifest payload.', 'wp2-update'),
                ], 400);
            }
        }

        $site_name     = get_bloginfo('name');
        $site_url      = esc_url_raw(home_url());
        $callback_url  = esc_url_raw(admin_url('admin.php?page=wp2-update-github-callback'));
        $setup_url     = esc_url_raw(admin_url('admin.php?page=wp2-update'));
        $webhook_url   = esc_url_raw(rest_url('wp2-update/v1/webhook'));
        $raw_name      = $request->get_param('name');
        $app_name      = $raw_name ? sanitize_text_field($raw_name) : '';

        $manifest = is_array($decoded_manifest) ? $decoded_manifest : [];
        $manifest['name'] = $app_name ?: ($manifest['name'] ?? $site_name . ' Updater');
        $manifest['url'] = $site_url;
        $manifest['redirect_url'] = $callback_url;

        $callback_urls = array_filter(array_map('esc_url_raw', (array) ($manifest['callback_urls'] ?? [])));
        if (!in_array($site_url, $callback_urls, true)) {
            $callback_urls[] = $site_url;
        }
        if (!in_array($callback_url, $callback_urls, true)) {
            $callback_urls[] = $callback_url;
        }
        $manifest['callback_urls'] = array_values(array_unique($callback_urls));

        $permissions = isset($manifest['default_permissions']) && is_array($manifest['default_permissions'])
            ? $manifest['default_permissions']
            : [];
        $permissions['contents'] = sanitize_text_field($permissions['contents'] ?? 'read');
        $permissions['metadata'] = sanitize_text_field($permissions['metadata'] ?? 'read');
        $manifest['default_permissions'] = $permissions;

        $events = isset($manifest['default_events']) && is_array($manifest['default_events'])
            ? array_map('sanitize_text_field', $manifest['default_events'])
            : [];
        if (!in_array('release', $events, true)) {
            $events[] = 'release';
        }
        $manifest['default_events'] = array_values(array_unique($events));

        $manifest['public'] = (bool) ($manifest['public'] ?? false);
        $manifest['setup_url'] = $setup_url;
        $manifest['setup_on_update'] = (bool) ($manifest['setup_on_update'] ?? false);
        $manifest['hook_attributes'] = [
            'url'    => $webhook_url,
            'active' => true,
        ];

        $manifest['description'] = isset($manifest['description'])
            ? sanitize_textarea_field($manifest['description'])
            : __('A GitHub App to manage updates for your WordPress site.', 'wp2-update');

        $manifest_json = wp_json_encode($manifest, JSON_UNESCAPED_SLASHES);
        if (false === $manifest_json) {
            return new WP_REST_Response([
                'message' => esc_html__('Unable to encode manifest.', 'wp2-update'),
            ], 500);
        }

        return new WP_REST_Response(
            [
                'manifest' => $manifest_json,
                'state'    => wp_create_nonce('wp2-manifest'),
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
