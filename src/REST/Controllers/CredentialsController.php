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
        $org_name = $request->get_param('organization');

        $manifest = [
            'name'             => $request->get_param('name') ?: get_bloginfo('name') . ' Updater',
            'url'              => home_url(),
            'redirect_url'     => admin_url('admin.php?page=wp2-update-github-callback'),
            'callback_urls'    => [home_url()],
            'public'           => false,
            'default_permissions' => [
                'contents' => 'read',
                'metadata' => 'read',
            ],
            'default_events'   => ['release'],
            'hook_attributes'  => [
                'url'    => rest_url('wp2-update/v1/webhook'),
                'active' => true,
            ],
            'setup_url'        => admin_url('admin.php?page=wp2-update'),
            'setup_on_update'  => false,
            'description'      => 'A GitHub App to manage updates for your WordPress site.',
        ];

        $manifest_json = wp_json_encode($manifest, JSON_UNESCAPED_SLASHES);
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
        $state = $request->get_param('state');
        if (!wp_verify_nonce($state, 'wp2-manifest')) {
            return new WP_REST_Response(['message' => esc_html__('Invalid state parameter.', 'wp2-update')], 400);
        }

        $code = $request->get_param('code');
        if (!$code) {
            return new WP_REST_Response(['message' => esc_html__('Authorization code is missing.', 'wp2-update')], 400);
        }

        $response = wp_remote_post("https://api.github.com/app-manifests/{$code}/conversions");
        if (is_wp_error($response)) {
            \WP2\Update\Utils\Logger::log('ERROR', 'GitHub OAuth exchange failed: ' . $response->get_error_message());
            return new WP_REST_Response(['message' => esc_html($response->get_error_message())], 500);
        }

        $body = wp_remote_retrieve_body($response);
        $credentials = json_decode($body, true);

        $this->credentialService->store_app_credentials([
            'id'              => $credentials['id'],
            'pem'             => $credentials['pem'],
            'installation_id' => $credentials['installation_id'],
            'webhook_secret'  => $credentials['webhook_secret'],
        ]);

        return new WP_REST_Response(['success' => true], 200);
    }
}