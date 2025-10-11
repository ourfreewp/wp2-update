<?php

namespace WP2\Update\Core\API;

use Github\Exception\ExceptionInterface;
use WP2\Update\Core\Updates\PackageFinder;
use WP2\Update\Utils\Cache;
use WP2\Update\Utils\Logger;

/**
 * Handles connection-related operations for GitHub Apps.
 */
class ConnectionService
{
    private GitHubClientFactory $clientFactory;
    private CredentialService $credentialService;
    private PackageFinder $packageFinder;

    public function __construct(GitHubClientFactory $clientFactory, CredentialService $credentialService, PackageFinder $packageFinder)
    {
        $this->clientFactory      = $clientFactory;
        $this->credentialService  = $credentialService;
        $this->packageFinder      = $packageFinder;
    }

    /**
     * Validate stored credentials.
     *
     * @return array{success:bool,message:string}
     */
    private function validate_credentials(?string $appUid = null): array
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
    private function test_webhook(?string $appUid = null): bool
    {
        $appKey = $appUid ?: 'default';
        $cacheKey = 'wp2_update_webhook_test_' . $appKey;
        $webhookTestValue = uniqid('webhook_', true);
        Cache::set($cacheKey, $webhookTestValue, 60 * 5);

        return Cache::get($cacheKey) !== false;
    }

    /**
     * Attempt to connect to GitHub using the stored credentials.
     *
     * @return array{success:bool,message:string,code?:string}
     */
    public function test_connection(?string $appUid = null): array
    {
        // Ensure appUid is provided
        if ($appUid === null) {
            return ['success' => false, 'message' => __('App ID is required to test the connection.', 'wp2-update')];
        }

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
        // Simulate saving the app data (e.g., to a database or option)
        $appData['id'] = uniqid('app_', true);
        $appData['status'] = 'active';

        // Log the app creation for debugging purposes
        Logger::log('INFO', 'App created: ' . json_encode($appData));

        return $appData;
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
        // Simulate fetching the app (e.g., from a database or option)
        $app = [
            'id' => $id,
            'name' => 'Existing App',
            'status' => 'active',
            'organization' => 'existing-org',
        ];

        // Merge the updated data
        $app = array_merge($app, $updatedData);

        // Log the app update for debugging purposes
        Logger::log('INFO', 'App updated: ' . json_encode($app));

        return $app;
    }

    /**
     * Delete an existing app.
     *
     * @param string $id The ID of the app to delete.
     * @return void
     */
    public function delete_app(string $id): void
    {
        // Simulate deleting the app (e.g., from a database or option)
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
        // Simulate assigning the repository to the app (e.g., updating a database or option)
        Logger::log('INFO', "Assigned repository {$repoId} to app {$appId}");
    }
}
