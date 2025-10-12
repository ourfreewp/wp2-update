<?php

namespace WP2\Update\Core\API;

use Github\Exception\ExceptionInterface;
use WP2\Update\Core\AppRepository;
use WP2\Update\Core\Updates\PackageFinder;
use WP2\Update\Utils\Cache;
use WP2\Update\Utils\Logger;
use WP2\Update\Utils\Formatting;
use function sanitize_text_field;
use function sanitize_title;
use function wp_json_encode;

/**
 * Handles connection-related operations for GitHub Apps.
 */
class ConnectionService
{
    private GitHubClientFactory $clientFactory;
    private CredentialService $credentialService;
    private PackageFinder $packageFinder;
    private AppRepository $appRepository;
    private RepositoryService $repositoryService;

    public function __construct(
        GitHubClientFactory $clientFactory,
        CredentialService $credentialService,
        PackageFinder $packageFinder,
        AppRepository $appRepository,
        RepositoryService $repositoryService
    )
    {
        $this->clientFactory      = $clientFactory;
        $this->credentialService  = $credentialService;
        $this->packageFinder      = $packageFinder;
        $this->appRepository      = $appRepository;
        $this->repositoryService  = $repositoryService;
    }

    /**
     * Validate stored credentials.
     *
     * @param string $appUid The unique identifier for the app.
     * @return array{success:bool,message:string}
     */
    private function validate_credentials(string $appUid): array
    {
        $credentials = $this->credentialService->get_stored_credentials($appUid);
        if (!$credentials) {
            return ['success' => false, 'message' => __('GitHub credentials are not configured.', 'wp2-update')];
        }

        if (empty($credentials['app_id']) || empty($credentials['private_key'])) {
            return ['success' => false, 'message' => __('Required credentials are missing.', 'wp2-update')];
        }

        return ['success' => true, 'message' => ''];
    }

    /**
     * Test webhook delivery for the provided app.
     */
    private function test_webhook(string $appUid): bool
    {
        $cacheKey = 'wp2_update_webhook_test_' . $appUid;
        $webhookTestValue = uniqid('webhook_', true);
        Cache::set($cacheKey, $webhookTestValue, 60 * 5);

        return Cache::get($cacheKey) !== false;
    }

    /**
     * Attempt to connect to GitHub using the stored credentials.
     *
     * @param string $appUid The unique identifier for the app.
     * @return array{success:bool,message:string,code?:string}
     */
    public function test_connection(string $appUid): array
    {
        $this->log_credentials_debug($appUid); // Log credentials for debugging

        try {
            $credentialValidation = $this->validate_credentials($appUid);
            if (!$credentialValidation['success']) {
                return $credentialValidation;
            }

            $credentials = $this->credentialService->get_stored_credentials($appUid);

            if (empty($credentials['installation_id'])) {
                return [
                    'success' => false,
                    'message' => __('GitHub App created, but no installation was detected yet. Install the app on your account, then refresh this page.', 'wp2-update'),
                    'code'    => 'missing-installation',
                ];
            }

            $client = $this->clientFactory->getInstallationClient($appUid);
            if (!$client) {
                return ['success' => false, 'message' => __('Unable to authenticate with GitHub.', 'wp2-update')];
            }

            $client->apps()->listRepositories();
        } catch (\RuntimeException $e) {
            Logger::log('ERROR', 'Credential data may be corrupted: ' . $e->getMessage());
            return ['success' => false, 'message' => __('Your saved credentials appear to be corrupted. Please try disconnecting and connecting again.', 'wp2-update')];
        } catch (ExceptionInterface $e) {
            Logger::log('ERROR', 'GitHub connection test failed: ' . $e->getMessage());
            return ['success' => false, 'message' => __('An error occurred while testing the connection.', 'wp2-update')];
        }

        return ['success' => true, 'message' => __('Connection to GitHub succeeded.', 'wp2-update')];
    }

    /**
     * Validates the GitHub connection by performing a series of checks.
     *
     * @return array{success:bool,steps:array,error?:string}
     */
    public function validate_connection(?string $appUid = null): array
    {
        $steps = [
            ['key' => 'jwt', 'text' => 'Minting JWT...', 'status' => 'pending'],
            ['key' => 'app_id', 'text' => 'Checking App ID...', 'status' => 'pending'],
            ['key' => 'installation', 'text' => 'Verifying Installation ID...', 'status' => 'pending'],
            ['key' => 'webhook', 'text' => 'Testing webhook delivery...', 'status' => 'pending'],
        ];

        try {
            $credentialValidation = $this->validate_credentials($appUid);
            if (!$credentialValidation['success']) {
                Logger::log('ERROR', $credentialValidation['message']);
                return ['success' => false, 'steps' => $steps, 'error' => $credentialValidation['message']];
            }

            $credentials = $this->credentialService->get_stored_credentials($appUid);

            if (empty($credentials['installation_id'])) {
                $steps[2]['status'] = 'error';
                return [
                    'success' => false,
                    'steps'   => $steps,
                    'error'   => __('No GitHub App installation was found. Install the app on your account, then retry.', 'wp2-update'),
                    'code'    => 'missing-installation',
                ];
            }

            $jwt = $this->clientFactory->createJwt($credentials['app_id'], $credentials['private_key']);
            if (!$jwt) {
                Logger::log('ERROR', 'Failed to mint JWT.');
                return ['success' => false, 'steps' => $steps, 'error' => __('Failed to mint JWT.', 'wp2-update')];
            }
            $steps[0]['status'] = 'success';
            $steps[1]['status'] = 'success';
            $steps[2]['status'] = 'success';

            if (!$this->test_webhook($appUid)) {
                Logger::log('ERROR', 'Webhook delivery test failed. Transient not cleared.');
                $steps[3]['status'] = 'error';
                return ['success' => false, 'steps' => $steps, 'error' => __('Webhook delivery test failed. Please check your webhook configuration.', 'wp2-update')];
            }
            $steps[3]['status'] = 'success';
        } catch (\Exception $e) {
            Logger::log('ERROR', 'Exception during connection validation: ' . $e->getMessage());
            return ['success' => false, 'steps' => $steps, 'error' => $e->getMessage()];
        }

        Logger::log('INFO', 'GitHub connection validated successfully.');
        return ['success' => true, 'steps' => $steps];
    }

    /**
     * Check if credentials are saved.
     */
    public function has_credentials(?string $appUid = null): bool
    {
        $credentials = $this->credentialService->get_stored_credentials($appUid);
        return !empty($credentials['app_id']) && !empty($credentials['private_key']);
    }

    /**
     * Provides the current connection status for the dashboard.
     *
     * @return array{status:string,message?:string,details?:array}
     */
    public function get_connection_status(?string $appUid = null): array
    {
        try {
            $credentials = $this->credentialService->get_stored_credentials($appUid);
        } catch (\RuntimeException $e) {
            Logger::log('ERROR', 'Unable to read stored credentials: ' . $e->getMessage());
            return [
                'status'  => 'connection_error',
                'message' => __('Stored credentials are inaccessible. Try disconnecting and reconnecting.', 'wp2-update'),
            ];
        }

        if (empty($credentials)) {
            return ['status' => 'not_configured'];
        }

        $managedPackages     = $this->packageFinder->get_managed_packages();
        $assignedRepositories = $credentials['managed_repositories'] ?? [];

        if (empty($credentials['installation_id'])) {
            return [
                'status'  => 'app_created',
                'message' => __('Your GitHub App is ready. Install it on your GitHub account to finish connecting.', 'wp2-update'),
                'details' => $this->build_app_created_details($credentials, $managedPackages),
            ];
        }

        $connectionTest = $this->test_connection($appUid);
        if (!$connectionTest['success']) {
            $status = $connectionTest['code'] ?? 'connection_error';

            if ($status === 'missing-installation') {
                return [
                    'status'  => 'app_created',
                    'message' => $connectionTest['message'],
                    'details' => $this->build_app_created_details($credentials, $managedPackages),
                ];
            }

            return [
                'status'  => 'connection_error',
                'message' => $connectionTest['message'] ?? __('An error occurred while testing the connection.', 'wp2-update'),
            ];
        }

        return [
            'status'  => 'installed',
            'message' => $connectionTest['message'] ?? __('Connection to GitHub succeeded.', 'wp2-update'),
            'details' => [
                'app_name'             => $credentials['name'] ?? '',
                'app_id'               => $credentials['app_id'] ?? '',
                'installation_id'      => $credentials['installation_id'] ?? '',
                'install_url'          => $this->build_install_url($credentials),
                'managed_repositories' => $this->format_managed_packages($managedPackages, $assignedRepositories),
            ],
        ];
    }

    private function build_install_url(array $credentials): ?string
    {
        $slug = trim((string) ($credentials['slug'] ?? ''));
        if ('' !== $slug) {
            return sprintf('https://github.com/apps/%s/installations/new', rawurlencode($slug));
        }

        $htmlUrl = trim((string) ($credentials['html_url'] ?? ''));
        if ('' !== $htmlUrl) {
            return rtrim($htmlUrl, '/') . '/installations/new';
        }

        return null;
    }

    /**
     * Annotate managed packages with assignment information.
     *
     * @param array<int,array> $packages
     * @param array<int,string> $assignedRepositories
     */
    private function format_managed_packages(array $packages, array $assignedRepositories): array
    {
        if (empty($packages)) {
            return [];
        }

        $assignedLookup = [];
        foreach ($assignedRepositories as $repo) {
            if ($repo !== '') {
                $assignedLookup[$repo] = true;
            }
        }

        return array_values(array_map(static function ($package) use ($assignedLookup) {
            $repo = $package['repo'] ?? '';
            $package['assigned'] = isset($assignedLookup[$repo]);
            return $package;
        }, $packages));
    }

    /**
     * Build detail payload for the "install app" state.
     */
    private function build_app_created_details(array $credentials, array $managedPackages): array
    {
        $assignedRepositories = $credentials['managed_repositories'] ?? [];

        return [
            'app_name'             => $credentials['name'] ?? '',
            'app_id'               => $credentials['app_id'] ?? '',
            'install_url'          => $this->build_install_url($credentials),
            'managed_repositories' => $this->format_managed_packages($managedPackages, $assignedRepositories),
        ];
    }

    /**
     * Save a new app.
     *
     * @param array $appData The app data to save.
     * @return array The saved app data.
     */
    public function save_app(array $appData): array
    {
        $name = sanitize_text_field((string) ($appData['name'] ?? ''));
        if ('' === $name) {
            throw new \InvalidArgumentException(__('App name is required.', 'wp2-update'));
        }

        $slug = sanitize_title($appData['slug'] ?? $name);
        $accountType = strtolower((string) ($appData['account_type'] ?? 'user'));
        if (!in_array($accountType, ['user', 'organization'], true)) {
            $accountType = 'user';
        }

        $organization = sanitize_text_field((string) ($appData['organization'] ?? ''));
        $status = (string) ($appData['status'] ?? 'pending');
        $allowedStatus = ['pending', 'active', 'inactive', 'requires_installation'];
        if (!in_array($status, $allowedStatus, true)) {
            $status = 'pending';
        }

        $managed = array_values(array_unique(array_filter(
            array_map(
                static fn($repo) => Formatting::normalize_repo(is_array($repo) ? ($repo['repo'] ?? null) : $repo),
                (array) ($appData['managed_repositories'] ?? [])
            )
        )));

        $manifest = $appData['manifest'] ?? null;
        if (is_array($manifest)) {
            $manifest = wp_json_encode($manifest);
        }

        $payload = array_filter(
            [
                'name'                 => $name,
                'slug'                 => $slug,
                'status'               => $status,
                'account_type'         => $accountType,
                'organization'         => $organization,
                'manifest'             => is_string($manifest) ? $manifest : null,
                'requires_installation'=> !empty($appData['requires_installation']),
                'managed_repositories' => $managed,
            ],
            static fn($value) => null !== $value
        );

        $saved = $this->appRepository->save($payload);
        Logger::log('INFO', 'App created: ' . json_encode($saved));

        return $saved;
    }

    /**
     * Update an existing app.
     *
     * @param string $id The ID of the app to update.
     * @param array $updatedData The data to update.
     * @return array The updated app data.
     */
    public function update_app(string $id, array $updatedData): array
    {
        $existing = $this->appRepository->find($id);
        if (!$existing) {
            throw new \RuntimeException(sprintf('App with ID %s not found.', $id));
        }

        $payload = ['id' => $id];

        if (array_key_exists('name', $updatedData)) {
            $name = sanitize_text_field((string) $updatedData['name']);
            if ($name !== '') {
                $payload['name'] = $name;
                $payload['slug'] = sanitize_title($updatedData['slug'] ?? $name);
            }
        }

        if (array_key_exists('status', $updatedData)) {
            $status = (string) $updatedData['status'];
            $allowedStatus = ['pending', 'active', 'inactive', 'requires_installation'];
            if (in_array($status, $allowedStatus, true)) {
                $payload['status'] = $status;
            }
        }

        if (array_key_exists('organization', $updatedData)) {
            $payload['organization'] = sanitize_text_field((string) $updatedData['organization']);
        }

        if (array_key_exists('account_type', $updatedData)) {
            $accountType = strtolower((string) $updatedData['account_type']);
            if (in_array($accountType, ['user', 'organization'], true)) {
                $payload['account_type'] = $accountType;
            }
        }

        if (array_key_exists('managed_repositories', $updatedData)) {
            $payload['managed_repositories'] = array_values(array_unique(array_filter(
                array_map(
                    static fn($repo) => Formatting::normalize_repo(is_array($repo) ? ($repo['repo'] ?? null) : $repo),
                    (array) $updatedData['managed_repositories']
                )
            )));
        }

        if (array_key_exists('manifest', $updatedData)) {
            $manifest = $updatedData['manifest'];
            if (is_array($manifest)) {
                $manifest = wp_json_encode($manifest);
            }
            $payload['manifest'] = is_string($manifest) ? $manifest : null;
        }

        $saved = $this->appRepository->save(array_merge($existing, $payload));
        Logger::log('INFO', 'App updated: ' . json_encode($saved));

        return $saved;
    }

    /**
     * Delete an existing app.
     *
     * @param string $id The ID of the app to delete.
     * @return void
     */
    public function delete_app(string $id): void
    {
        $this->appRepository->delete($id);
        $this->credentialService->clear_stored_credentials($id);
        Logger::log('INFO', 'App deleted: ' . $id);
    }

    /**
     * Assign a repository to an app.
     *
     * @param string $appId The ID of the app.
     * @param string $repoId The ID of the repository.
     * @return void
     */
    public function assign_package(string $appId, string $repoId): void
    {
        $app = $this->appRepository->find($appId);
        if (!$app) {
            throw new \RuntimeException(sprintf('App with ID %s not found.', $appId));
        }

        $normalizedRepo = Formatting::normalize_repo($repoId);
        if (!$normalizedRepo) {
            throw new \InvalidArgumentException(__('Invalid repository identifier.', 'wp2-update'));
        }

        $managed = isset($app['managed_repositories']) && is_array($app['managed_repositories'])
            ? $app['managed_repositories']
            : [];

        if (in_array($normalizedRepo, $managed, true)) {
            Logger::log('INFO', sprintf('Repository %s already assigned to app %s.', $normalizedRepo, $appId));
            return;
        }

        $managed[] = $normalizedRepo;
        $this->repositoryService->update_managed_repositories($appId, array_values(array_unique($managed)));

        Logger::log('INFO', sprintf('Assigned repository %s to app %s.', $normalizedRepo, $appId));
    }

    /**
     * Unassign a repository from an app.
     *
     * @param string $appId The ID of the app.
     * @param string $repoId The ID of the repository.
     * @return void
     */
    public function unassign_package(string $appId, string $repoId): void
    {
        $app = $this->appRepository->find($appId);
        if (!$app) {
            throw new \RuntimeException(sprintf('App with ID %s not found.', $appId));
        }

        $managed = isset($app['managed_repositories']) && is_array($app['managed_repositories'])
            ? $app['managed_repositories']
            : [];

        $normalizedRepo = Formatting::normalize_repo($repoId);
        if (!$normalizedRepo || !in_array($normalizedRepo, $managed, true)) {
            Logger::log('INFO', sprintf('Repository %s is not assigned to app %s.', $normalizedRepo, $appId));
            return;
        }

        $managed = array_values(array_filter($managed, static fn($repo) => $repo !== $normalizedRepo));
        $this->repositoryService->update_managed_repositories($appId, $managed);

        Logger::log('INFO', sprintf('Unassigned repository %s from app %s.', $normalizedRepo, $appId));
    }

    private function log_credentials_debug(?string $appUid = null): void
    {
        $credentials = $this->credentialService->get_stored_credentials($appUid);
        if (!$credentials) {
            Logger::log('DEBUG', 'No credentials found for appUid: ' . ($appUid ?? 'default'));
            return;
        }

        Logger::log('DEBUG', 'Credentials for appUid: ' . ($appUid ?? 'default'));
        Logger::log('DEBUG', 'App ID: ' . ($credentials['app_id'] ?? 'N/A'));
        Logger::log('DEBUG', 'Installation ID: ' . ($credentials['installation_id'] ?? 'N/A'));
        Logger::log('DEBUG', 'Private Key: ' . (isset($credentials['private_key']) ? '[REDACTED]' : 'N/A'));
    }
}
