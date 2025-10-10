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
        $encryptionKey = $credentials['encryption_key'] ?? $this->get_encryption_key();
        if (!$encryptionKey) {
            throw new \RuntimeException('Cannot store credentials without an encryption key.');
        }

        $iv = openssl_random_pseudo_bytes(16);
        $encryptedKey = base64_encode($iv . openssl_encrypt($credentials['private_key'], 'AES-256-CBC', $encryptionKey, 0, $iv));

        $iv_secret = openssl_random_pseudo_bytes(16);
        $encryptedSecret = base64_encode($iv_secret . openssl_encrypt($credentials['webhook_secret'], 'AES-256-CBC', $encryptionKey, 0, $iv_secret));

        update_option(Config::OPTION_CREDENTIALS, [
            'name'            => sanitize_text_field($credentials['name'] ?? ''),
            'app_id'          => absint($credentials['app_id'] ?? 0),
            'installation_id' => absint($credentials['installation_id'] ?? 0),
            'private_key'     => $encryptedKey,
            'webhook_secret'  => $encryptedSecret,
            'encryption_key'  => $encryptionKey, // Store the key itself
        ]);

        // New logic to update installation_id dynamically
        if (empty($credentials['installation_id'])) {
            // Removed call to fetchInstallationId as the method is no longer implemented
            Logger::log('WARNING', 'Installation ID is missing and cannot be fetched automatically.');
        }
    }

    /**
     * Updates the stored installation ID once the GitHub App is installed.
     */
    public function update_installation_id(int $installationId): void
    {
        $installationId = absint($installationId);
        if ($installationId <= 0) {
            return;
        }

        $record = get_option(Config::OPTION_CREDENTIALS, []);
        if (empty($record)) {
            return;
        }

        if (isset($record['installation_id']) && (int) $record['installation_id'] === $installationId) {
            return;
        }

        $record['installation_id'] = $installationId;
        update_option(Config::OPTION_CREDENTIALS, $record);
    }

    /**
     * Retrieves GitHub App credentials from WordPress options.
     *
     * @return array{name:string,app_id:string,installation_id:string,private_key:string}
     */
    public function get_stored_credentials(): array
    {
        $record = get_option(Config::OPTION_CREDENTIALS, []);
        if (empty($record['private_key'])) {
            return []; // No credentials stored, return empty.
        }

        $encryptionKey = $this->get_encryption_key();
        if (!$encryptionKey) {
            // This is the crucial part: we have credentials but no key to decrypt them.
            // This is now the ONLY way a user can get stuck, and it's a valid error.
            throw new \RuntimeException('Credentials are encrypted, but no encryption key is available.');
        }

        // Decrypt the private key
        $decoded = base64_decode($record['private_key']);
        $iv = substr($decoded, 0, 16);
        $encryptedData = substr($decoded, 16);
        $decryptedKey = openssl_decrypt($encryptedData, 'AES-256-CBC', $encryptionKey, 0, $iv) ?: '';

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
        delete_option(Config::OPTION_CREDENTIALS);
    }

    /**
     * Retrieves and decrypts the webhook secret from the stored credentials.
     *
     * @return string The raw webhook secret, or an empty string if not found.
     */
    public function get_decrypted_webhook_secret(): string
    {
        $record = get_option(Config::OPTION_CREDENTIALS, []);
        if (empty($record['webhook_secret'])) {
            return '';
        }

        $encryptionKey = $this->get_encryption_key();
        if (!$encryptionKey) {
            return '';
        }

        $decoded = base64_decode($record['webhook_secret']);
        $iv = substr($decoded, 0, 16);
        $encryptedSecret = substr($decoded, 16);

        return openssl_decrypt($encryptedSecret, 'AES-256-CBC', $encryptionKey, 0, $iv) ?: '';
    }

    /**
     * Retrieves the encryption key, prioritizing the one stored in the database.
     * Falls back to wp-config.php constants for advanced users or backward compatibility.
     *
     * @return string|null The encryption key or null if none is found.
     */
    private function get_encryption_key(): ?string
    {
        $credentials = get_option(Config::OPTION_CREDENTIALS, []);

        // 1. Prioritize the key saved in the database.
        if (!empty($credentials['encryption_key'])) {
            return $credentials['encryption_key'];
        }

        // 2. Fallback to a custom constant in wp-config.php.
        if (defined('WP2_UPDATE_ENCRYPTION_KEY') && !empty(WP2_UPDATE_ENCRYPTION_KEY)) {
            return WP2_UPDATE_ENCRYPTION_KEY;
        }

        // 3. Fallback to the default WordPress AUTH_KEY.
        if (defined('AUTH_KEY') && !empty(AUTH_KEY)) {
            return AUTH_KEY;
        }

        return null;
    }

    /**
     * Retrieves the installation token for a specific installation ID.
     *
     * @param int $installationId The installation ID.
     * @return string|null The installation token, or null on failure.
     */
    public function get_installation_token(int $installationId): ?string
    {
        $credentials = $this->get_stored_credentials();
        if (empty($credentials['private_key']) || empty($credentials['app_id'])) {
            Logger::log('ERROR', 'Missing credentials for generating installation token.');
            return null;
        }

        $jwt = $this->generate_jwt($credentials['app_id'], $credentials['private_key']);
        if (!$jwt) {
            Logger::log('ERROR', 'Failed to generate JWT for installation token.');
            return null;
        }

        $response = \WP2\Update\Utils\HttpClient::post(
            "https://api.github.com/app/installations/{$installationId}/access_tokens",
            [],
            [
                'Authorization' => 'Bearer ' . $jwt,
                'Accept'        => 'application/vnd.github+json',
            ]
        );

        if (!$response) {
            Logger::log('ERROR', 'Failed to retrieve installation token.');
            return null;
        }

        return $response['token'] ?? null;
    }

    /**
     * Generates a JWT for GitHub App authentication.
     *
     * @param string $appId The GitHub App ID.
     * @param string $privateKey The private key for the GitHub App.
     * @return string|null The generated JWT, or null on failure.
     */
    private function generate_jwt(string $appId, string $privateKey): ?string
    {
        $issuedAt = time();
        $payload = [
            'iat' => $issuedAt,
            'exp' => $issuedAt + 540,
            'iss' => $appId,
        ];

        try {
            return \Firebase\JWT\JWT::encode($payload, $privateKey, 'RS256');
        } catch (\Exception $e) {
            Logger::log('ERROR', 'Failed to generate JWT: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieves the stored installation ID.
     *
     * @return int|null The installation ID, or null if not set.
     */
    public function get_installation_id(): ?int
    {
        $credentials = get_option(Config::OPTION_CREDENTIALS, []);
        return !empty($credentials['installation_id']) ? (int) $credentials['installation_id'] : null;
    }
}
