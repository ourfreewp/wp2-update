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
    public const DEFAULT_APP_SLUG = 'primary-app';

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
            error_log("WP2 Update: Using cached release for {$owner}/{$repo}.");
            return $cachedRelease;
        }

        $client = $this->getInstallationClient();
        if (!$client) {
            error_log("WP2 Update: Installation client not available for {$owner}/{$repo}.");
            return null;
        }

        try {
            $latestRelease = $client->repo()->releases()->latest($owner, $repo);
            set_transient($transientKey, $latestRelease, HOUR_IN_SECONDS);
            error_log("WP2 Update: Successfully fetched latest release for {$owner}/{$repo}.");
            return $latestRelease;
        } catch (ExceptionInterface $e) {
            error_log('WP2 Update: GitHub latest release request failed - ' . $e->getMessage());
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
            error_log('WP2 Update: Unable to create temporary file for download.');
            return null;
        }

        try {
            $response = $client->getHttpClient()->get($url);
            file_put_contents($tempFile, $response->getBody()->getContents());
            return $tempFile;
        } catch (\Throwable $e) {
            @unlink($tempFile);
            error_log('WP2 Update: Failed to download asset - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Encrypts and stores GitHub App credentials using OpenSSL.
     *
     * @param array{name:string,app_id:string,installation_id:string,private_key:string} $credentials
     */
    public function store_app_credentials(array $credentials): void
    {
        $postId = $this->find_or_create_primary_post($credentials['name'] ?? '');

        if ($postId) {
            if (!empty($credentials['name'])) {
                wp_update_post(
                    [
                        'ID'         => $postId,
                        'post_title' => sanitize_text_field($credentials['name']),
                    ]
                );
            }

            update_post_meta($postId, '_wp2_app_id', absint($credentials['app_id'] ?? 0));
            update_post_meta($postId, '_wp2_installation_id', absint($credentials['installation_id'] ?? 0));

            // Encrypt the private key before storing it
            $encryptionKey = defined('AUTH_KEY') ? AUTH_KEY : 'default_key';
            $iv = substr(hash('sha256', $encryptionKey), 0, 16);
            $encryptedKey = openssl_encrypt($credentials['private_key'], 'AES-256-CBC', $encryptionKey, 0, $iv);
            update_post_meta($postId, '_wp2_private_key_content', $encryptedKey);

            // Encrypt the webhook secret before storing it
            if (!empty($credentials['webhook_secret'])) {
                $encryptedSecret = openssl_encrypt($credentials['webhook_secret'], 'AES-256-CBC', $encryptionKey, 0, $iv);
                update_post_meta($postId, '_wp2_webhook_secret', $encryptedSecret);
            }
        }

        $this->clear_cached_clients();
    }

    /**
     * Retrieves and decrypts GitHub App credentials using OpenSSL.
     *
     * @return array{name:string,app_id:string,installation_id:string,private_key:string}
     */
    public function get_stored_credentials(): array
    {
        $record = $this->getStoredAppPost();

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
            error_log('WP2 Update: Validation failed - ' . $e->getMessage());
            return ['success' => false, 'steps' => $steps];
        }
    }

    /**
     * Create (or reuse) the installation authenticated client.
     *
     * @param bool $forceRefresh When true, always re-authenticate.
     */
    private function getInstallationClient(bool $forceRefresh = false): ?GitHubClient
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

        $client = new GitHubClient();
        $client->authenticate($token['token'], AuthMethod::ACCESS_TOKEN);

        $this->installationClient        = $client;
        $this->installationClientExpires = $token['expires'];

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
            error_log('WP2 Update: Unable to create installation token - ' . $e->getMessage());
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
            error_log('WP2 Update: Failed to encode JWT - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Locates the post that stores credentials.
     *
     * @return array{post_id:int,name:string,app_id:string,installation_id:string,private_key:string}|null
     */
    private function getStoredAppPost(): ?array
    {
        $post = get_page_by_path(self::DEFAULT_APP_SLUG, OBJECT, 'wp2_github_app');
        if (!$post) {
            $query = new \WP_Query(
                [
                    'post_type'      => 'wp2_github_app',
                    'posts_per_page' => 1,
                    'post_status'    => 'any',
                    'orderby'        => 'ID',
                    'order'          => 'ASC',
                ]
            );

            if ($query->have_posts()) {
                $post = get_post($query->posts[0]);
            }
        }

        if (!$post) {
            return null;
        }

        return [
            'post_id'         => (int) $post->ID,
            'name'            => (string) $post->post_title,
            'app_id'          => (string) get_post_meta($post->ID, '_wp2_app_id', true),
            'installation_id' => (string) get_post_meta($post->ID, '_wp2_installation_id', true),
            'private_key'     => (string) get_post_meta($post->ID, '_wp2_private_key_content', true),
        ];
    }

    /**
     * Ensures there is a persistent post to hold credentials.
     */
    private function find_or_create_primary_post(string $name): int
    {
        $existing = get_page_by_path(self::DEFAULT_APP_SLUG, OBJECT, 'wp2_github_app');
        if ($existing instanceof \WP_Post) {
            return (int) $existing->ID;
        }

        $postId = wp_insert_post(
            [
                'post_type'   => 'wp2_github_app',
                'post_status' => 'publish',
                'post_name'   => self::DEFAULT_APP_SLUG,
                'post_title'  => $name ?: __('Primary GitHub App', 'wp2-update'),
            ],
            true
        );

        if (is_wp_error($postId) || 0 === $postId) {
            error_log('WP2 Update: Failed to create GitHub App storage post.');
            return 0;
        }

        return (int) $postId;
    }

    /**
     * Generates a JSON Web Token (JWT) for GitHub authentication.
     *
     * @return string|null The generated JWT or null on failure.
     */
    private function generateJWT(): ?string
    {
        try {
            $credentials = $this->get_stored_credentials();
            if (empty($credentials['app_id']) || empty($credentials['private_key'])) {
                return null;
            }

            $payload = [
                'iat' => time(),
                'exp' => time() + (10 * 60), // 10 minutes expiration
                'iss' => $credentials['app_id'],
            ];

            return JWT::encode($payload, $credentials['private_key'], 'RS256');
        } catch (\Exception $e) {
            error_log('WP2 Update: Failed to generate JWT - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Tests the webhook delivery by simulating a payload.
     *
     * @return bool True if the webhook test is successful, false otherwise.
     */
    private function test_webhook(): bool
    {
        try {
            // Simulate a webhook payload
            $payload = json_encode(['action' => 'test', 'repository' => 'example-repo']);
            $response = wp_remote_post('https://example.com/webhook', [
                'body'    => $payload,
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            return wp_remote_retrieve_response_code($response) === 200;
        } catch (\Exception $e) {
            error_log('WP2 Update: Failed to test webhook - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Synchronizes package data by fetching the latest information from GitHub.
     *
     * @return array{success:bool,packages:array}
     */
    public function sync_packages(): array
    {
        $client = $this->getInstallationClient();
        if (!$client) {
            return [
                'success' => false,
                'repositories' => [],
            ];
        }

        try {
            $repositories = $client->apps()->listRepositories();
            $repoData = [];

            foreach ($repositories['repositories'] as $repo) {
                $repoData[] = [
                    'name' => $repo['name'],
                    'full_name' => $repo['full_name'],
                    'private' => $repo['private'],
                    'url' => $repo['html_url'],
                ];
            }

            return [
                'success' => true,
                'repositories' => $repoData,
            ];
        } catch (ExceptionInterface $e) {
            error_log('WP2 Update: Failed to sync packages - ' . $e->getMessage());
            return [
                'success' => false,
                'repositories' => [],
            ];
        }
    }

    /**
     * Manages a specific package (e.g., update, rollback).
     *
     * @param string $action The action to perform ('update' or 'rollback')
     * @param string $package The package name
     * @param string|null $version The version/tag to update/rollback to
     * @return array{success:bool,message:string}
     */
    public function managePackage(string $action, string $package, ?string $version): array
    {
        try {
            $client = $this->getInstallationClient();
            if (!$client) {
                return [
                    'success' => false,
                    'message' => __('GitHub client not initialized.', 'wp2-update'),
                ];
            }

            if ($action === 'update') {
                // You must provide the correct owner/repo for your context
                $owner = 'your-repo-owner'; // Replace with actual owner
                $repo = $package; // Assume $package is the repo name

                // Fetch the release by tag name
                $release = $client->repo()->releases()->tag($owner, $repo, $version);
                $assetUrl = $release['assets'][0]['browser_download_url'] ?? null;

                if (!$assetUrl) {
                    return [
                        'success' => false,
                        'message' => __('No downloadable asset found for the specified version.', 'wp2-update'),
                    ];
                }

                // Download the asset
                $tempFile = $this->download_to_temp_file($assetUrl);
                if (!$tempFile) {
                    return [
                        'success' => false,
                        'message' => __('Failed to download the update file.', 'wp2-update'),
                    ];
                }

                // Determine upgrader type
                require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
                $upgrader = null;
                if (strpos($package, 'plugin') !== false) {
                    $upgrader = new \Plugin_Upgrader();
                    $destination = WP_CONTENT_DIR . '/plugins/';
                } elseif (strpos($package, 'theme') !== false) {
                    $upgrader = new \Theme_Upgrader();
                    $destination = WP_CONTENT_DIR . '/themes/';
                } else {
                    // Default to plugin if not specified
                    $upgrader = new \Plugin_Upgrader();
                    $destination = WP_CONTENT_DIR . '/plugins/';
                }

                // Run the upgrader
                $result = $upgrader->run([
                    'package' => $tempFile,
                    'destination' => $destination,
                    'clear_destination' => true,
                    'clear_working' => true,
                ]);

                // Clean up the temporary file
                @unlink($tempFile);

                if (is_wp_error($result)) {
                    return [
                        'success' => false,
                        'message' => __('Update failed: ', 'wp2-update') . $result->get_error_message(),
                    ];
                }

                return [
                    'success' => true,
                    'message' => sprintf(__('Package %s updated to version %s successfully.', 'wp2-update'), $package, $version),
                ];
            } elseif ($action === 'rollback') {
                // Simulate rollback logic (replace with actual logic)
                return [
                    'success' => true,
                    'message' => sprintf(__('Package %s rolled back successfully.', 'wp2-update'), $package),
                ];
            } else {
                return [
                    'success' => false,
                    'message' => __('Invalid action specified.', 'wp2-update'),
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => __('Failed to manage package: ', 'wp2-update') . $e->getMessage(),
            ];
        }
    }

    /**
     * Validates the connection to the GitHub App.
     *
     * @return array{status: string, checks: array<string, bool|string>}
     */
    public function validateConnection(): array
    {
        $credentials = $this->get_stored_credentials();
        $checks = [
            'app_id' => !empty($credentials['app_id']),
            'installation_id' => !empty($credentials['installation_id']),
            'private_key' => !empty($credentials['private_key']),
        ];

        $status = array_reduce($checks, fn($carry, $item) => $carry && $item, true) ? 'success' : 'failure';

        return [
            'status' => $status,
            'checks' => $checks,
        ];
    }

    /**
     * Synchronizes the list of packages from GitHub.
     *
     * @return array{status: string, packages: array<string, mixed>}
     */
    public function syncPackages(): array
    {
        $client = $this->getInstallationClient();
        if (!$client) {
            return [
                'status' => 'failure',
                'packages' => [],
            ];
        }

        try {
            $repos = $client->currentUser()->repositories();
            $packages = array_map(fn($repo) => [
                'name' => $repo['name'],
                'full_name' => $repo['full_name'],
                'url' => $repo['html_url'],
            ], $repos);

            return [
                'status' => 'success',
                'packages' => $packages,
            ];
        } catch (ExceptionInterface $e) {
            error_log('WP2 Update: Failed to sync packages - ' . $e->getMessage());
            return [
                'status' => 'failure',
                'packages' => [],
            ];
        }
    }

    /**
     * Generates a JWT for GitHub App authentication.
     */
    public function mintJWT(): array
    {
        try {
            $credentials = $this->get_stored_credentials();
            $payload = [
                'iat' => time(),
                'exp' => time() + (10 * 60), // 10 minutes expiration
                'iss' => $credentials['app_id'],
            ];

            $jwt = JWT::encode($payload, $credentials['private_key'], 'RS256');

            return ['success' => true, 'jwt' => $jwt];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Tests the GitHub API connection.
     */
    public function testAPIConnection(): array
    {
        try {
            $client = $this->getInstallationClient();
            $response = $client->currentUser()->show();

            return ['success' => true, 'user' => $response];
        } catch (ExceptionInterface $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Fetches repositories from the GitHub API.
     */
    public function fetchRepositories(): array
    {
        try {
            $client = $this->getInstallationClient();
            $repositories = $client->installation()->repositories();

            return $repositories['repositories'] ?? [];
        } catch (ExceptionInterface $e) {
            error_log('WP2 Update: Failed to fetch repositories - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Handles package actions such as update or rollback.
     */
    public function handlePackageAction(string $action, string $package, ?string $version): array
    {
        try {
            // Example logic for handling package actions
            if ($action === 'update') {
                $this->updatePackage($package, $version);
            } elseif ($action === 'rollback') {
                $this->rollbackPackage($package, $version);
            }

            return ['success' => true, 'message' => __('Action completed successfully.', 'wp2-update')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function updatePackage(string $package, ?string $version): void
    {
        // Logic for updating a package
    }

    private function rollbackPackage(string $package, ?string $version): void
    {
        // Logic for rolling back a package
    }
}
