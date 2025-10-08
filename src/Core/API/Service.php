<?php

namespace WP2\Update\Core\API;

use Firebase\JWT\JWT;
use Github\AuthMethod;
use Github\Client as GitHubClient;
use Github\Exception\ExceptionInterface;

/**
 * Handles GitHub authentication and lightweight API helpers.
 */
class Service
{
    private ?GitHubClient $installationClient = null;
    private ?int $installationClientExpires   = null;

    /**
     * Fetches the latest release for a repository.
     */
    public function get_latest_release(string $owner, string $repo): ?array
    {
        $transientKey = "wp2_latest_release_{$owner}_{$repo}";
        $cachedRelease = get_transient($transientKey);

        if ($cachedRelease !== false) {
            $this->log_error("Using cached release for {$owner}/{$repo}.");
            return $cachedRelease;
        }

        $client = $this->getInstallationClient();
        if (!$client) {
            $this->log_error("Installation client not available for {$owner}/{$repo}.");
            return null;
        }

        try {
            $latestRelease = $client->repo()->releases()->latest($owner, $repo);
            set_transient($transientKey, $latestRelease, HOUR_IN_SECONDS);
            $this->log_error("Successfully fetched latest release for {$owner}/{$repo}.");
            return $latestRelease;
        } catch (ExceptionInterface $e) {
            $this->log_error('GitHub latest release request failed - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Downloads a protected asset to a temporary file.
     */
    public function download_to_temp_file(string $url): ?string
    {
        if (!function_exists('wp_tempnam')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $client = $this->getInstallationClient();
        if (!$client) {
            return null;
        }

        $tempFile = wp_tempnam($url);
        if (!$tempFile) {
            $this->log_error('Unable to create temporary file for download.');
            return null;
        }

        try {
            $response = $client->getHttpClient()->get($url);
            file_put_contents($tempFile, $response->getBody()->getContents());
            return $tempFile;
        } catch (\Throwable $e) {
            @unlink($tempFile);
            $this->log_error('Failed to download asset - ' . $e->getMessage());
            throw new \RuntimeException(__('Failed to download the update file. Please check the URL or your network connection.', 'wp2-update'));
        }
    }

    /**
     * Encrypts and stores GitHub App credentials using OpenSSL.
     *
     * @param array{name:string,app_id:string,installation_id:string,private_key:string} $credentials
     */
    public function store_app_credentials(array $credentials): void
    {
        $optionKey = 'wp2_github_app_credentials';

        $encryptionKey = defined('AUTH_KEY') ? AUTH_KEY : 'default_key';
        $iv = substr(hash('sha256', $encryptionKey), 0, 16);
        $encryptedKey = openssl_encrypt($credentials['private_key'], 'AES-256-CBC', $encryptionKey, 0, $iv);

        $data = [
            'name'            => sanitize_text_field($credentials['name'] ?? ''),
            'app_id'          => absint($credentials['app_id'] ?? 0),
            'installation_id' => absint($credentials['installation_id'] ?? 0),
            'private_key'     => $encryptedKey,
        ];

        if (!empty($credentials['webhook_secret'])) {
            $data['webhook_secret'] = openssl_encrypt($credentials['webhook_secret'], 'AES-256-CBC', $encryptionKey, 0, $iv);
        }

        update_option($optionKey, $data);
        $this->clear_cached_clients();
    }

    /**
     * Retrieves GitHub App credentials from WordPress options.
     *
     * @return array{name:string,app_id:string,installation_id:string,private_key:string}
     */
    public function get_stored_credentials(): array
    {
        $optionKey = 'wp2_github_app_credentials';
        $record = get_option($optionKey, []);

        $encryptionKey = defined('AUTH_KEY') ? AUTH_KEY : 'default_key';
        $decryptedKey = isset($record['private_key'])
            ? openssl_decrypt($record['private_key'], 'AES-256-CBC', $encryptionKey, 0, substr($encryptionKey, 0, 16))
            : '';

        return [
            'name'            => $record['name'] ?? '',
            'app_id'          => $record['app_id'] ?? '',
            'installation_id' => $record['installation_id'] ?? '',
            'private_key'     => $decryptedKey,
        ];
    }

    /**
     * Clears any cached API clients or tokens.
     */
    public function clear_cached_clients(): void
    {
        $this->installationClient        = null;
        $this->installationClientExpires = null;
    }

    /**
     * Attempt to connect to GitHub using the stored credentials.
     *
     * @return array{success:bool,message:string}
     */
    public function test_connection(): array
    {
        $credentials = $this->getStoredAppPost();
        if (!$credentials) {
            return [
                'success' => false,
                'message' => __('No GitHub App credentials have been saved yet.', 'wp2-update'),
            ];
        }

        if (empty($credentials['app_id']) || empty($credentials['installation_id']) || empty($credentials['private_key'])) {
            return [
                'success' => false,
                'message' => __('App ID, Installation ID, or Private Key is missing.', 'wp2-update'),
            ];
        }

        $client = $this->getInstallationClient(true);
        if (!$client) {
            return [
                'success' => false,
                'message' => __('Unable to authenticate with GitHub. Double-check the credentials.', 'wp2-update'),
            ];
        }

        try {
            $client->apps()->listRepositories();
        } catch (ExceptionInterface $e) {
            return [
                'success' => false,
                'message' => sprintf(__('GitHub responded with an error: %s', 'wp2-update'), $e->getMessage()),
            ];
        }

        return [
            'success' => true,
            'message' => __('Connection to GitHub succeeded.', 'wp2-update'),
        ];
    }

    /**
     * Validates the GitHub connection by performing a series of checks.
     *
     * @return array{success:bool,steps:array}
     */
    public function validate_connection(): array
    {
        $steps = [
            ['key' => 'jwt', 'text' => 'Minting JWT...', 'status' => 'pending'],
            ['key' => 'app_id', 'text' => 'Checking App ID...', 'status' => 'pending'],
            ['key' => 'installation', 'text' => 'Verifying Installation ID...', 'status' => 'pending'],
            ['key' => 'webhook', 'text' => 'Testing webhook delivery...', 'status' => 'pending'],
        ];

        try {
            // Step 1: Mint JWT
            $jwt = $this->generateJWT();
            if (!$jwt) {
                $steps[0]['status'] = 'error';
                return ['success' => false, 'steps' => $steps];
            }
            $steps[0]['status'] = 'success';

            // Step 2: Check App ID
            $credentials = $this->get_stored_credentials();
            if (empty($credentials['app_id'])) {
                $steps[1]['status'] = 'error';
                return ['success' => false, 'steps' => $steps];
            }
            $steps[1]['status'] = 'success';

            // Step 3: Verify Installation ID
            if (empty($credentials['installation_id'])) {
                $steps[2]['status'] = 'error';
                return ['success' => false, 'steps' => $steps];
            }
            $steps[2]['status'] = 'success';

            // Step 4: Test Webhook
            $webhookTest = $this->test_webhook();
            if (!$webhookTest) {
                $steps[3]['status'] = 'error';
                return ['success' => false, 'steps' => $steps];
            }
            $steps[3]['status'] = 'success';

            return ['success' => true, 'steps' => $steps];
        } catch (\Exception $e) {
            return ['success' => false, 'steps' => $steps];
        }
    }

    /**
     * Validates the full GitHub connection by performing a sequence of checks.
     *
     * @return array{success:bool,steps:array}
     */
    public function validate_full_connection(): array
    {
        $steps = [
            ['key' => 'private_key', 'text' => 'Validating private key format...', 'status' => 'pending'],
            ['key' => 'jwt', 'text' => 'Minting JWT...', 'status' => 'pending'],
            ['key' => 'app_id', 'text' => 'Checking App ID...', 'status' => 'pending'],
            ['key' => 'installation', 'text' => 'Verifying Installation ID...', 'status' => 'pending'],
            ['key' => 'api_call', 'text' => 'Performing test API call...', 'status' => 'pending'],
        ];

        try {
            // Step 1: Validate private key format
            $credentials = $this->get_stored_credentials();
            if (empty($credentials['private_key'])) {
                $steps[0]['status'] = 'error';
                return ['success' => false, 'steps' => $steps];
            }
            $steps[0]['status'] = 'success';

            // Step 2: Mint JWT
            $jwt = $this->createJwt($credentials['app_id'], $credentials['private_key']);
            if (!$jwt) {
                $steps[1]['status'] = 'error';
                return ['success' => false, 'steps' => $steps];
            }
            $steps[1]['status'] = 'success';

            // Step 3: Check App ID
            if (empty($credentials['app_id'])) {
                $steps[2]['status'] = 'error';
                return ['success' => false, 'steps' => $steps];
            }
            $steps[2]['status'] = 'success';

            // Step 4: Verify Installation ID
            if (empty($credentials['installation_id'])) {
                $steps[3]['status'] = 'error';
                return ['success' => false, 'steps' => $steps];
            }
            $steps[3]['status'] = 'success';

            // Step 5: Perform test API call
            $client = $this->getInstallationClient(true);
            if (!$client) {
                $steps[4]['status'] = 'error';
                return ['success' => false, 'steps' => $steps];
            }

            try {
                $client->apps()->listRepositories();
                $steps[4]['status'] = 'success';
            } catch (ExceptionInterface $e) {
                $steps[4]['status'] = 'error';
                return ['success' => false, 'steps' => $steps];
            }

            return ['success' => true, 'steps' => $steps];
        } catch (\Throwable $e) {
            $this->log_error('Validation failed - ' . $e->getMessage());
            return ['success' => false, 'steps' => $steps];
        }
    }

    /**
     * Create (or reuse) the installation authenticated client.
     *
     * @param bool $forceRefresh When true, always re-authenticate.
     */
    public function getInstallationClient(bool $forceRefresh = false): ?GitHubClient
    {
        if (!$forceRefresh && $this->installationClient && $this->installationClientExpires && $this->installationClientExpires > (time() + 60)) {
            return $this->installationClient;
        }

        $credentials = $this->getStoredAppPost();
        if (!$credentials || empty($credentials['app_id']) || empty($credentials['installation_id']) || empty($credentials['private_key'])) {
            return null;
        }

        $token = $this->createInstallationToken($credentials);

        if (!$token) {
            return null;
        }

        $this->installationClient = new GitHubClient();
        $this->installationClient->authenticate($token, AuthMethod::ACCESS_TOKEN);
        $this->installationClientExpires = time() + 3600; // Tokens are valid for 1 hour

        return $this->installationClient;
    }

    /**
     * Generates an installation token for the stored credentials.
     *
     * @param array{name:string,app_id:string,installation_id:string,private_key:string,post_id:int} $credentials
     * @return array{token:string,expires:int}|null
     */
    private function createInstallationToken(array $credentials): ?array
    {
        $jwt = $this->createJwt($credentials['app_id'], $credentials['private_key']);
        if (!$jwt) {
            return null;
        }

        $client = new GitHubClient();
        $client->authenticate($jwt, AuthMethod::JWT);

        try {
            $result = $client->apps()->createInstallationToken((int) $credentials['installation_id']);
        } catch (ExceptionInterface $e) {
            $this->log_error('Unable to create installation token - ' . $e->getMessage());
            return null;
        }

        if (empty($result['token'])) {
            return null;
        }

        $expiresAt = isset($result['expires_at']) ? strtotime($result['expires_at']) : time() + 3600;

        return [
            'token'   => $result['token'],
            'expires' => $expiresAt,
        ];
    }

    /**
     * Builds the JWT used for app authentication.
     */
    private function createJwt(string $appId, string $privateKey): ?string
    {
        $appId = trim($appId);
        $privateKey = trim($privateKey);

        if ('' === $appId || '' === $privateKey) {
            return null;
        }

        $issuedAt = time();
        $payload  = [
            'iat' => $issuedAt,
            'exp' => $issuedAt + 540,
            'iss' => $appId,
        ];

        try {
            return JWT::encode($payload, $privateKey, 'RS256');
        } catch (\Throwable $e) {
            $this->log_error('Failed to encode JWT - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generates a JWT for GitHub authentication.
     *
     * @return string|null
     */
    private function generateJWT(): ?string
    {
        $credentials = $this->get_stored_credentials();
        if (empty($credentials['app_id']) || empty($credentials['private_key'])) {
            $this->log_error('Missing app_id or private_key for JWT generation.');
            return null;
        }

        $payload = [
            'iat' => time(),
            'exp' => time() + (10 * 60),
            'iss' => $credentials['app_id'],
        ];

        try {
            return JWT::encode($payload, $credentials['private_key'], 'RS256');
        } catch (\Throwable $e) {
            $this->log_error('Failed to encode JWT - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Tests the webhook connection.
     *
     * @return bool
     */
    private function test_webhook(): bool
    {
        // Placeholder implementation for webhook testing.
        $this->log_error('test_webhook is not implemented yet.');
        return false;
    }

    /**
     * Removes the unused post-based credential storage methods.
     */
    private function find_or_create_primary_post(string $name): int
    {
        error_log('find_or_create_primary_post is deprecated and no longer used.');
        return 0;
    }

    private function getStoredAppPost(): ?array
    {
        error_log('getStoredAppPost is deprecated and no longer used.');
        return null;
    }

    /**
     * Clears stored GitHub App credentials from the options table.
     */
    public function clear_stored_credentials(): void
    {
        delete_option('wp2_github_app_credentials');
        $this->clear_cached_clients();
    }

    /**
     * Note: The `wp2_github_app_credentials` option key is used to store
     * GitHub App credentials in WordPress options. This replaces the
     * previous implementation that relied on custom post types.
     */

    private function log_error(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        error_log("[WP2 Update] [{$timestamp}] {$message}");
    }

    /**
     * Fetch a specific release by version from GitHub.
     */
    public function get_release_by_version(string $repoSlug, string $version): ?array
    {
        try {
            $client = $this->getInstallationClient();
            if (!$client) {
                throw new \Exception('GitHub client not initialized.');
            }

            [$owner, $repo] = explode('/', $repoSlug);
            $releases = $client->api('repo')->releases()->all($owner, $repo);

            foreach ($releases as $release) {
                if ($release['tag_name'] === $version) {
                    return $release;
                }
            }

            return null; // Release not found
        } catch (\Exception $e) {
            $this->log_error('Error fetching release: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetches the repositories for the authenticated installation.
     */
    public function get_repositories(): array
    {
        try {
            $client = $this->getInstallationClient();
            if (!$client) {
                throw new \Exception('GitHub client not initialized.');
            }

            // Use the search API as a fallback to fetch repositories
            $repositories = $client->api('search')->repositories('user:your-github-username');
            return $repositories['items'] ?? [];
        } catch (\Exception $e) {
            $this->log_error('Error fetching repositories: ' . $e->getMessage());
            return [];
        }
    }
}
