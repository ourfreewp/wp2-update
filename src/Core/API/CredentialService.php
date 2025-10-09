<?php

namespace WP2\Update\Core\API;

use WP2\Update\Config;
use WP2\Update\Utils\Logger;

/**
 * Handles GitHub App credentials.
 */
class CredentialService
{
    /**
     * Encrypts and stores GitHub App credentials using OpenSSL.
     *
     * @param array{name:string,app_id:string,installation_id:string,private_key:string} $credentials
     */
    public function store_app_credentials(array $credentials): void
    {
        $optionKey = Config::OPTION_CREDENTIALS;
        $encryptionKey = defined('AUTH_KEY') ? AUTH_KEY : throw new \RuntimeException('WordPress security keys not defined.');
        $iv = openssl_random_pseudo_bytes(16); // Generate a unique, random IV
        $encryptedKey = base64_encode($iv . openssl_encrypt($credentials['private_key'], 'AES-256-CBC', $encryptionKey, 0, $iv));

        $data = [
            'name'            => sanitize_text_field($credentials['name'] ?? ''),
            'app_id'          => absint($credentials['app_id'] ?? 0),
            'installation_id' => absint($credentials['installation_id'] ?? 0),
            'private_key'     => $encryptedKey,
        ];

        if (!empty($credentials['webhook_secret'])) {
            $iv = openssl_random_pseudo_bytes(16); // Generate a new IV for the webhook secret
            $data['webhook_secret'] = base64_encode($iv . openssl_encrypt($credentials['webhook_secret'], 'AES-256-CBC', $encryptionKey, 0, $iv));
        }

        update_option($optionKey, $data);
    }

    /**
     * Retrieves GitHub App credentials from WordPress options.
     *
     * @return array{name:string,app_id:string,installation_id:string,private_key:string}
     */
    public function get_stored_credentials(): array
    {
        $optionKey = Config::OPTION_CREDENTIALS;
        $record = get_option($optionKey, []);

        $encryptionKey = defined('AUTH_KEY') ? AUTH_KEY : throw new \RuntimeException('WordPress security keys not defined.');
        $decryptedKey = '';

        if (isset($record['private_key'])) {
            $decoded = base64_decode($record['private_key']);
            $iv = substr($decoded, 0, 16);
            $encryptedKey = substr($decoded, 16);
            $decryptedKey = openssl_decrypt($encryptedKey, 'AES-256-CBC', $encryptionKey, 0, $iv) ?: '';
        }

        return [
            'name'            => $record['name'] ?? '',
            'app_id'          => $record['app_id'] ?? '',
            'installation_id' => $record['installation_id'] ?? '',
            'private_key'     => $decryptedKey,
        ];
    }

    /**
     * Clears stored GitHub App credentials from the options table.
     */
    public function clear_stored_credentials(): void
    {
        delete_option('wp2_github_app_credentials');
    }

    /**
     * Retrieves and decrypts the webhook secret from the stored credentials.
     *
     * @return string The raw webhook secret, or an empty string if not found.
     */
    public function get_decrypted_webhook_secret(): string
    {
        $record = get_option('wp2_github_app_credentials', []);
        if (empty($record['webhook_secret'])) {
            return '';
        }

        $encryptionKey = defined('AUTH_KEY') ? AUTH_KEY : throw new \RuntimeException('WordPress security keys not defined.');
        $decoded = base64_decode($record['webhook_secret']);
        $iv = substr($decoded, 0, 16);
        $encryptedSecret = substr($decoded, 16);

        return openssl_decrypt($encryptedSecret, 'AES-256-CBC', $encryptionKey, 0, $iv) ?: '';
    }

    /**
     * Clears stored credentials.
     *
     * @return bool True on success, false on failure.
     */
    public function clear_credentials(): bool
    {
        // Clear stored credentials from the database or other storage.
        delete_option('wp2_update_github_credentials');
        return true;
    }

    /**
     * Exchanges an authorization code for an access token.
     *
     * @param string $code The authorization code.
     * @return string|null The access token, or null on failure.
     */
    public function exchange_code_for_token(string $code): ?string
    {
        $url = 'https://github.com/login/oauth/access_token';
        $clientId = get_option('wp2_update_github_client_id');
        $clientSecret = get_option('wp2_update_github_client_secret');

        $response = wp_remote_post($url, [
            'body' => [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'code' => $code,
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            Logger::log('ERROR', 'Failed to exchange code for token: ' . $response->get_error_message());
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['access_token'] ?? null;
    }
}