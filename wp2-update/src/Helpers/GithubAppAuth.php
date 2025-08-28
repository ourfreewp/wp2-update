<?php
namespace WP2\Update\Helpers;

use WP2\Update\Utils\Log;

class GithubAppAuth {
    /**
     * Get a valid installation access token, caching it until it expires.
     * @return string|null The access token or null on failure.
     */
    public static function get_token(): ?string {
        // Check for required constants
        if (!defined('WP2_GITHUB_APP_ID') || !defined('WP2_GITHUB_INSTALLATION_ID') || !defined('WP2_GITHUB_PRIVATE_KEY_PATH')) {
            Log::add('GitHub App authentication failed: missing constants.', 'error', 'github-api');
            return null;
        }
        $app_id = WP2_GITHUB_APP_ID;
        $installation_id = WP2_GITHUB_INSTALLATION_ID;
        $private_key_path = WP2_GITHUB_PRIVATE_KEY_PATH;
        if (!file_exists($private_key_path)) {
            Log::add('GitHub App authentication failed: private key not found.', 'error', 'github-api');
            return null;
        }
        $private_key = file_get_contents($private_key_path);
        $now = time();
        $payload = [
            'iat' => $now - 60,
            'exp' => $now + (10 * 60),
            'iss' => $app_id,
        ];
        $jwt = self::generate_jwt($payload, $private_key);
        if (!$jwt) {
            Log::add('GitHub App authentication failed: JWT generation error.', 'error', 'github-api');
            return null;
        }
        // Request installation access token
        $url = "https://api.github.com/app/installations/{$installation_id}/access_tokens";
        $args = [
            'headers' => [
                'Authorization' => "Bearer {$jwt}",
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'WP2Update/1.0',
            ],
            'body' => '',
            'timeout' => 15,
        ];
        $response = wp_remote_post($url, $args);
        if (is_wp_error($response) || 201 !== wp_remote_retrieve_response_code($response)) {
            $error = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response);
            Log::add('GitHub App token request failed: ' . $error, 'error', 'github-api');
            return null;
        }
        $data = json_decode(wp_remote_retrieve_body($response));
        return $data->token ?? null;
    }

    /**
     * Generate a JWT for GitHub App authentication.
     */
    private static function generate_jwt(array $payload, string $private_key): ?string {
        if (!extension_loaded('openssl')) {
            return null;
        }
        $header = [ 'alg' => 'RS256', 'typ' => 'JWT' ];
        $segments = [
            self::base64url_encode(json_encode($header)),
            self::base64url_encode(json_encode($payload)),
        ];
        $signing_input = implode('.', $segments);
        $signature = '';
        $success = openssl_sign($signing_input, $signature, $private_key, 'sha256');
        if (!$success) {
            return null;
        }
        $segments[] = self::base64url_encode($signature);
        return implode('.', $segments);
    }

    private static function base64url_encode($data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
