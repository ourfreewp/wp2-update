<?php

namespace WP2\Update\Rest\Controllers;

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
        $private_key     = sanitize_textarea_field($request->get_param('wp2_private_key'));
        $webhook_secret  = sanitize_text_field($request->get_param('wp2_webhook_secret'));

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

    public function rest_get_connect_url(WP_REST_Request $request): WP_REST_Response {
        $org_name = $request->get_param('organization');

        $manifest = [
            'name'             => $request->get_param('name') ?: get_bloginfo('name') . ' Updater',
            'url'              => home_url(),
            'redirect_url'     => admin_url('tools.php?page=wp2-update-callback'),
            'callback_urls'    => [ home_url() ],
            'public'           => false,
            'default_permissions' => ['contents' => 'read', 'metadata' => 'read'],
            'default_events'   => ['release'],
        ];

        $url = 'https://github.com/settings/apps/new?manifest=' . rawurlencode(wp_json_encode($manifest));
        if ($org_name) {
            $url .= '&organization_name=' . rawurlencode($org_name);
        }

        return new WP_REST_Response(['url' => esc_url_raw($url)], 200);
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
        $code = $request->get_param('code');
        if (!$code) {
            return new WP_REST_Response(['message' => esc_html__('Authorization code is missing.', 'wp2-update')], 400);
        }

        $response = wp_remote_post("https://api.github.com/app-manifests/{$code}/conversions");
        if (is_wp_error($response)) {
            \WP2\Update\Utils\Logger::log('ERROR', 'GitHub OAuth exchange failed: ' . $response->get_error_message());
            return new WP_REST_Response(['message' => esc_html($response->get_error_message())], 500);
        }

        $body        = wp_remote_retrieve_body($response);
        $credentials = json_decode($body, true);

        $this->credentialService->store_app_credentials($credentials);

        return new WP_REST_Response(['success' => true], 200);
    }
}