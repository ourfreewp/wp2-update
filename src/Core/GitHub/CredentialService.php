<?php

namespace WP2\Update\Core\GitHub;

use WP2\Update\Core\AppRepository;
use WP2\Update\Utils\Cache;
use WP2\Update\Utils\Logger;

/**
 * Handles GitHub App credentials.
 */
class CredentialService
{
    private AppRepository $repository;
    private ?RepositoryService $repositoryService;
    private ?string $customEncryptionKey = null;

    public function __construct(AppRepository $repository, ?RepositoryService $repositoryService = null)
    {
        $this->repository = $repository;
        $this->repositoryService = $repositoryService;
    }

    /**
     * Persist credentials for a GitHub App.
     *
     * @param array $credentials App data including secrets.
     * @return array Sanitised app payload for API responses.
     */
    public function store_app_credentials(array $credentials): array
    {
        $appUid   = isset($credentials['app_uid']) ? sanitize_text_field($credentials['app_uid']) : '';
        $existing = $appUid ? $this->repository->find($appUid) : null;

        $encryptionKey = isset($credentials['encryption_key']) && !empty($credentials['encryption_key'])
            ? sanitize_text_field($credentials['encryption_key'])
            : $this->fallback_encryption_key();

        if (!$encryptionKey) {
            Logger::log('ERROR', 'Encryption key is missing. Cannot store credentials.');
            throw new \RuntimeException('Cannot store credentials without an encryption key.');
        }

        $name = sanitize_text_field($credentials['name'] ?? ($existing['name'] ?? ''));
        $slug = sanitize_title($credentials['slug'] ?? ($existing['slug'] ?? $name));

        $privateKeyPlain = isset($credentials['private_key']) ? (string) $credentials['private_key'] : '';
        $webhookPlain    = isset($credentials['webhook_secret']) ? (string) $credentials['webhook_secret'] : '';

        $record = [
            'id'                    => $existing['id'] ?? ($appUid !== '' ? $appUid : wp_generate_uuid4()),
            'name'                  => $name,
            'slug'                  => $slug,
            'html_url'              => esc_url_raw($credentials['html_url'] ?? ($existing['html_url'] ?? '')),
            'account_type'          => in_array($credentials['account_type'] ?? '', ['user', 'organization'], true) ? $credentials['account_type'] : ($existing['account_type'] ?? 'user'),
            'org_slug'              => sanitize_title($credentials['org_slug'] ?? $credentials['organization'] ?? ($existing['org_slug'] ?? '')),
            'app_id'                => absint($credentials['app_id'] ?? ($existing['app_id'] ?? 0)),
            'installation_id'       => absint($credentials['installation_id'] ?? ($existing['installation_id'] ?? 0)),
            'private_key'           => $privateKeyPlain !== '' ? $this->encrypt_secret($privateKeyPlain, $encryptionKey) : ($existing['private_key'] ?? ''),
            'webhook_secret'        => $webhookPlain !== '' ? $this->encrypt_secret($webhookPlain, $encryptionKey) : ($existing['webhook_secret'] ?? ''),
            'managed_repositories'  => $existing['managed_repositories'] ?? [],
            'status'                => $existing['status'] ?? ($credentials['installation_id'] ? 'installed' : 'pending'),
        ];

        // Handle requires_installation flag
        if (!empty($credentials['requires_installation'])) {
            $record['status'] = 'requires_installation';
            $record['installation_url'] = esc_url_raw($credentials['installation_url'] ?? '');
        }

        // Update managed_repositories if installed
        if ($record['status'] === 'installed' && !empty($record['installation_id'])) {
            $record['managed_repositories'] = $this->fetch_managed_repositories($record['installation_id']);

            // Log the update for debugging purposes
            Logger::log('INFO', sprintf('Updated managed repositories for app %s (%s).', $record['name'], $record['id']));
        }

        $saved = $this->repository->save($record);

        // Invalidate token cache for the app
        $cacheKey = 'github_installation_token_' . $record['id'];
        delete_transient($cacheKey);

        // Log token invalidation
        Logger::log('INFO', sprintf('Invalidated GitHub token cache for app %s (%s).', $record['name'], $record['id']));

        if (empty($saved['installation_id'])) {
            Logger::log('WARNING', sprintf('Installation ID missing for app %s (%s).', $saved['name'], $saved['id']));
        }

        return $this->prepare_app_response($saved);
    }

    /**
     * Update the installation id for a given app.
     */
    public function update_installation_id(string $appUid, int $installationId): void
    {
        $installationId = absint($installationId);
        if ($installationId <= 0) {
            return;
        }

        $app = $this->repository->find($appUid);
        if (!$app) {
            return;
        }

        if (!empty($app['installation_id']) && (int) $app['installation_id'] === $installationId) {
            return;
        }

        $app['installation_id'] = $installationId;
        $app['status']          = 'installed';

        $this->repository->save($app);
    }

    /**
     * Update app credentials.
     *
     * @param string $appUid The ID of the app to update.
     * @param array $updates The fields to update.
     * @return array The updated app data.
     */
    public function update_app_credentials(string $appUid, array $updates): array
    {
        $app = $this->repository->find($appUid);

        if (!$app) {
            throw new \RuntimeException("App with ID {$appUid} not found.");
        }

        $updatedApp = array_merge($app, $updates);
        $updatedApp['updated_at'] = current_time('mysql');

        $this->repository->save($updatedApp);

        return $updatedApp;
    }

    /**
     * Retrieve decrypted credentials for an app.
     *
     * @param string $appUid The unique identifier for the app.
     * @return array{name:string,app_id:string,installation_id:string,private_key:string,slug:string,html_url:string,id:string}
     * @throws \InvalidArgumentException If $appUid is not provided.
     */
    public function get_stored_credentials(?string $appUid = null): array
    {
        $resolvedUid = $this->resolve_app_uid($appUid);
        if (!$resolvedUid) {
            return [];
        }

        $app = $this->repository->find($resolvedUid);
        if (!$app || empty($app['private_key'])) {
            return [];
        }

        $encryptionKey = $this->fallback_encryption_key();
        if (!$encryptionKey) {
            throw new \RuntimeException('Credentials are encrypted, but no encryption key is available.');
        }

        $privateKey = $this->decrypt_secret($app['private_key'], $encryptionKey);

        if ($privateKey === '') {
            Logger::log('WARNING', sprintf('Stored credentials for app %s could not be decrypted. Resetting.', $app['id'] ?? $resolvedUid));

            unset($app['encryption_key']);
            $app['private_key']     = '';
            $app['webhook_secret']  = '';
            $app['installation_id'] = 0;
            $app['status']          = 'pending';

            $this->repository->save($app);
            return [];
        }

        return [
            'id'              => $app['id'] ?? $resolvedUid,
            'name'            => $app['name'] ?? '',
            'app_id'          => $app['app_id'] ?? '',
            'installation_id' => $app['installation_id'] ?? '',
            'slug'            => $app['slug'] ?? '',
            'html_url'        => $app['html_url'] ?? '',
            'private_key'     => $privateKey,
            'managed_repositories' => $app['managed_repositories'] ?? [],
        ];
    }

    /**
     * Remove stored credentials.
     */
    public function clear_stored_credentials(?string $appUid = null): void
    {
        if ($appUid) {
            $this->repository->delete($appUid);
            Cache::delete('github_installation_token_' . $appUid);
            return;
        }

        foreach ($this->repository->all() as $app) {
            if (!empty($app['id'])) {
                Cache::delete('github_installation_token_' . $app['id']);
            }
        }

        $this->repository->delete_all();
        Cache::delete('github_installation_token_default');
    }

    /**
     * Get and decrypt the webhook secret for a given app.
     */
    public function get_decrypted_webhook_secret(?string $appUid = null): string
    {
        $app = $this->resolve_app($appUid);
        if (!$app || empty($app['webhook_secret'])) {
            return '';
        }

        $encryptionKey = $this->fallback_encryption_key();
        if (!$encryptionKey) {
            return '';
        }

        return $this->decrypt_secret($app['webhook_secret'], $encryptionKey);
    }

    /**
     * Return a map of app ids to webhook secrets.
     *
     * @return array<string,string>
     */
    public function get_all_webhook_secrets(): array
    {
        $map = [];

        foreach ($this->repository->all() as $app) {
            $encryptionKey = $this->fallback_encryption_key();
            if (!$encryptionKey || empty($app['webhook_secret'])) {
                continue;
            }

            $secret = $this->decrypt_secret($app['webhook_secret'], $encryptionKey);
            if ($secret !== '') {
                $map[$app['id']] = $secret;
            }
        }

        return $map;
    }

    /**
     * Retrieve summaries for all stored apps (no secrets).
     *
     * @return array<int,array<string,mixed>>
     */
    public function get_app_summaries(): array
    {
        $apps = $this->repository->all();
        Logger::log('INFO', 'Fetched apps from repository: ' . json_encode($apps));

        return array_map([$this, 'prepare_app_response'], $apps);
    }

    /**
     * Retrieve installation id for an app.
     */
    public function get_installation_id(?string $appUid = null): ?int
    {
        $app = $this->resolve_app($appUid);

        return $app && !empty($app['installation_id']) ? (int) $app['installation_id'] : null;
    }

    /**
     * Generate an installation token for the provided app.
     */
    public function get_installation_token(int $installationId, ?string $appUid = null): ?string
    {
        $credentials = $this->get_stored_credentials($appUid);
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
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $jwt,
                    'Accept'        => 'application/vnd.github+json',
                ],
            ]
        );

        if (!$response) {
            Logger::log('ERROR', 'Failed to retrieve installation token.');
            return null;
        }

        return $response['token'] ?? null;
    }

    /**
     * Helper: ensure an app is available.
     *
     * @param string $appUid The unique identifier for the app.
     * @return array|null The app data, or null if not found.
     */
    private function resolve_app(?string $appUid): ?array
    {
        $resolvedUid = $this->resolve_app_uid($appUid, false);
        return $resolvedUid ? $this->repository->find($resolvedUid) : null;
    }

    private function encrypt_secret(string $value, string $key): string
    {
        if ($value === '') {
            return '';
        }

        $iv = openssl_random_pseudo_bytes(16);
        return base64_encode($iv . openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv));
    }

    private function decrypt_secret(string $value, string $key): string
    {
        if ($value === '') {
            return '';
        }

        $decoded = base64_decode($value);
        if ($decoded === false || strlen($decoded) < 17) {
            return '';
        }

        $iv            = substr($decoded, 0, 16);
        $encryptedData = substr($decoded, 16);

        return openssl_decrypt($encryptedData, 'AES-256-CBC', $key, 0, $iv) ?: '';
    }

    /**
     * Resolve the app UID to operate on.
     *
     * @param string|null $appUid            Preferred app identifier.
     * @param bool        $requireCredentials Whether the app must have stored credentials.
     */
    private function resolve_app_uid(?string $appUid, bool $requireCredentials = true): ?string
    {
        $candidate = is_string($appUid) ? trim($appUid) : '';
        if ('' !== $candidate) {
            return $candidate;
        }

        $apps = $this->repository->all();
        if (!is_array($apps) || empty($apps)) {
            return null;
        }

        foreach ($apps as $app) {
            if (!is_array($app)) {
                continue;
            }

            $id = isset($app['id']) ? (string) $app['id'] : '';
            if ('' === $id) {
                continue;
            }

            if ($requireCredentials) {
                $hasPrivateKey = !empty($app['private_key']);
                $hasAppId      = !empty($app['app_id']);
                if (!$hasPrivateKey || !$hasAppId) {
                    continue;
                }
            }

            return $id;
        }

        return null;
    }

    /**
     * Prepare an app record for JSON responses.
     */
    private function prepare_app_response(array $app): array
    {
        $status = $app['status'] ?? ($app['installation_id'] ? 'installed' : 'pending');

        return [
            'id'                   => $app['id'],
            'name'                 => $app['name'] ?? '',
            'slug'                 => $app['slug'] ?? '',
            'html_url'             => $app['html_url'] ?? '',
            'account_type'         => $app['account_type'] ?? 'user',
            'org_slug'             => $app['org_slug'] ?? '',
            'app_id'               => (int) ($app['app_id'] ?? 0),
            'installation_id'      => (int) ($app['installation_id'] ?? 0),
            'managed_repositories' => array_values($app['managed_repositories'] ?? []),
            'status'               => $status,
            'created_at'           => $app['created_at'] ?? '',
            'updated_at'           => $app['updated_at'] ?? '',
            'install_url'          => $this->build_install_url_from_app($app),
        ];
    }

    /**
     * Prepare app response for summaries.
     *
     * @param array $app The app data.
     * @return array The prepared app response.
     */
    private function prepare_summary_response(array $app): array
    {
        return [
            'id' => $app['id'],
            'name' => $app['name'],
            'slug' => $app['slug'],
            'webhookStatus' => !empty($app['webhook_secret']) ? 'active' : 'inactive',
            'managedRepoCount' => count($app['managed_repositories'] ?? []),
            'createdAt' => $app['created_at'] ?? null,
            'updatedAt' => $app['updated_at'] ?? null,
        ];
    }

    /**
     * Set a custom encryption key for credentials.
     *
     * @param string $customKey The custom encryption key to use.
     */
    public function set_custom_encryption_key(string $customKey): void
    {
        if (empty($customKey) || strlen($customKey) < 16) {
            throw new \InvalidArgumentException('Custom encryption key must be at least 16 characters long.');
        }

        $this->customEncryptionKey = $customKey;
    }

    /**
     * Retrieve the encryption key, prioritizing the custom key if set.
     */
    private function get_encryption_key(): ?string
    {
        if (!empty($this->customEncryptionKey)) {
            return $this->customEncryptionKey;
        }

        if (defined('WP2_UPDATE_ENCRYPTION_KEY') && !empty(WP2_UPDATE_ENCRYPTION_KEY)) {
            return WP2_UPDATE_ENCRYPTION_KEY;
        }

        if (defined('AUTH_KEY') && !empty(AUTH_KEY)) {
            return AUTH_KEY;
        }

        return null;
    }

    /**
     * Update fallback_encryption_key to use get_encryption_key.
     */
    private function fallback_encryption_key(): ?string
    {
        return $this->get_encryption_key();
    }

    /**
     * Generates a JWT for GitHub App authentication.
     */
    private function generate_jwt(string $appId, string $privateKey): ?string
    {
        $issuedAt = time();
        $payload  = [
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

    private function build_install_url_from_app(array $app): ?string
    {
        $slug = trim((string) ($app['slug'] ?? ''));
        if ('' !== $slug) {
            return sprintf('https://github.com/apps/%s/installations/new', rawurlencode($slug));
        }

        $htmlUrl = trim((string) ($app['html_url'] ?? ''));
        if ('' !== $htmlUrl) {
            return rtrim($htmlUrl, '/') . '/installations/new';
        }

        return null;
    }

    /**
     * Delete app credentials.
     *
     * @param string $appUid The ID of the app to delete.
     * @return void
     */
    public function delete_app_credentials(string $appUid): void
    {
        $app = $this->repository->find($appUid);

        if (!$app) {
            throw new \RuntimeException("App with ID {$appUid} not found.");
        }

        $this->repository->delete($appUid);
    }

    /**
     * Refresh the list of managed repositories for a given app.
     *
     * @param string $appUid The unique identifier for the app.
     * @param int $installationId The installation ID associated with the app.
     * @return void
     */
    public function refresh_managed_repositories(string $appUid, int $installationId): void
    {
        $app = $this->repository->find($appUid);

        if (!$app) {
            Logger::log('WARNING', sprintf('App with UID %s not found. Cannot refresh repositories.', $appUid));
            return;
        }

        if (!$this->repositoryService) {
            Logger::log('ERROR', 'RepositoryService is not available. Cannot refresh repositories.');
            return;
        }

        try {
            $repositories = $this->repositoryService->get_repositories_by_installation($installationId);
            $app['managed_repositories'] = $repositories;
            $this->repository->save($app);

            Logger::log('INFO', sprintf('Managed repositories updated for app %s (%s).', $app['name'], $appUid));
        } catch (\Exception $e) {
            Logger::log('ERROR', sprintf('Failed to refresh repositories for app %s (%s): %s', $app['name'], $appUid, $e->getMessage()));
        }
    }

    /**
     * Fetch managed repositories for a given installation ID.
     *
     * @param int $installationId The GitHub App installation ID.
     * @return array The list of managed repositories.
     */
    private function fetch_managed_repositories(int $installationId): array
    {
        $repositories = $this->repositoryService->get_repositories_by_installation($installationId) ?? [];
        if (!is_array($repositories)) {
            return [];
        }

        $managed = [];
        foreach ($repositories as $repository) {
            if (!empty($repository['full_name'])) {
                $managed[] = $repository['full_name'];
            }
        }

        return $managed;
    }

    /**
     * Fetch managed repositories for a given app.
     *
     * @param string $appUid The unique ID of the app.
     * @return array The list of managed repositories.
     */
    public function get_managed_repositories(string $appUid): array
    {
        $app = $this->repository->find($appUid);
        if (!$app) {
            return [];
        }

        $managed = $app['managed_repositories'] ?? [];
        if (!is_array($managed)) {
            return [];
        }

        return array_values(
            array_filter(
                $managed,
                static fn($value) => is_string($value) && $value !== ''
            )
        );
    }

    /**
     * Set the RepositoryService instance.
     * @param RepositoryService $repositoryService
     */
    public function setRepositoryService(RepositoryService $repositoryService): void
    {
        $this->repositoryService = $repositoryService;
    }

    /**
     * Public wrapper for encrypt_secret (for testing purposes).
     */
    public function test_encrypt_secret(string $value, string $key): string
    {
        return $this->encrypt_secret($value, $key);
    }

    /**
     * Retrieve all stored apps.
     *
     * @return array The list of all apps.
     */
    public function get_all_apps(): array
    {
        return $this->repository->find_all();
    }

    /**
     * Deletes stored credentials for a GitHub App.
     *
     * @param string $appUid The unique identifier for the app.
     * @return bool True if the credentials were deleted, false otherwise.
     */
    public function delete_credentials(string $appUid): bool
    {
        $app = $this->repository->find($appUid);
        if (!$app) {
            Logger::log('WARNING', sprintf('No credentials found for app %s.', $appUid));
            return false;
        }

        try {
            $this->repository->delete($appUid);
            Logger::log('INFO', sprintf('Deleted credentials for app %s.', $appUid));
            return true;
        } catch (\Exception $e) {
            Logger::log('ERROR', sprintf('Failed to delete credentials for app %s: %s', $appUid, $e->getMessage()));
            return false;
        }
    }
}
