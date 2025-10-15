<?php

namespace WP2\Update\Services\Github;

use WP2\Update\Utils\Logger;

/**
 * Handles GitHub connection-related logic.
 */
class ConnectionService {
    /**
     * Generates the GitHub App manifest data.
     *
     * @param string $app_id The GitHub App ID.
     * @param string $name The name of the GitHub App.
     * @param string|null $account_type The account type (user or organization).
     * @param string|null $org_slug The organization slug.
     * @return array The generated manifest data.
     */
    public function generate_manifest_data(string $app_id, string $name, ?string $account_type = null, ?string $org_slug = null): array {
        // Example implementation for generating manifest data
        $manifest = [
            'app_id' => $app_id,
            'name' => $name,
            'account_type' => $account_type,
            'organization' => $org_slug,
            'permissions' => [
                'contents' => 'read',
                'metadata' => 'read',
            ],
            'events' => ['push', 'pull_request'],
        ];

        Logger::log('INFO', 'Generated GitHub App manifest data.', ['manifest' => $manifest]);

        return $manifest;
    }

    /**
     * Retrieves all webhook secrets.
     *
     * @return array An array of webhook secrets.
     */
    public function get_all_webhook_secrets(): array {
        $appData = new \WP2\Update\Data\AppData(); // Ensure AppData is properly injected in the future
        $apps = $appData->all();

        $secrets = [];
        foreach ($apps as $app) {
            if (!empty($app['webhook_secret'])) {
                $secrets[$app['id']] = $app['webhook_secret'];
            }
        }

        return $secrets;
    }

    /**
     * Updates the installation ID for a given app.
     *
     * @param string $app_id The app ID.
     * @param int $installation_id The installation ID to update.
     */
    public function update_installation_id(string $app_id, int $installation_id): void {
        $appData = new \WP2\Update\Data\AppData(); // Ensure AppData is properly injected in the future
        $apps = $appData->all();

        if (isset($apps[$app_id])) {
            $apps[$app_id]['installation_id'] = $installation_id;
            $appData->save($apps);
        }
    }

    /**
     * Retrieves the connection status for a GitHub App.
     *
     * @param string $app_id The GitHub App ID.
     * @return array The connection status data.
     */
    public function get_connection_status(string $app_id): array {
        $appData = $this->get_connection_data();
        $apps = $appData->all();

        if (!isset($apps[$app_id])) {
            throw new \Exception('App not found.');
        }

        // Example logic to check connection status (replace with actual GitHub API call)
        $isConnected = true; // Replace with real connection check
        $lastChecked = date('Y-m-d H:i:s');

        return [
            'status' => $isConnected ? 'connected' : 'disconnected',
            'last_checked' => $lastChecked,
        ];
    }

    /**
     * Retrieves all apps and their managed repositories.
     *
     * @return array An array of apps with their managed repositories.
     */
    public function all(): array {
        $appData = new \WP2\Update\Data\AppData(); // Ensure AppData is properly injected in the future
        $apps = $appData->all();

        foreach ($apps as &$app) {
            $app['managed_repositories'] = $app['managed_repositories'] ?? [];
        }

        return $apps;
    }

    /**
     * Retrieves connection data for a specific app.
     *
     * @return \WP2\Update\Data\AppData The AppData instance for managing connections.
     */
    public function get_connection_data(): \WP2\Update\Data\AppData {
        return new \WP2\Update\Data\AppData();
    }

    /**
     * Retrieves a summary of all GitHub Apps.
     *
     * @return array An array of app summaries.
     */
    public function get_app_summaries(): array {
        $appData = $this->get_connection_data();
        $apps = $appData->all();

        return array_map(function ($app) {
            return [
                'id' => $app['id'],
                'name' => $app['name'],
                'account_type' => $app['account_type'],
                'repositories' => count($app['managed_repositories'] ?? []),
            ];
        }, $apps);
    }

    /**
     * Creates a new app record and validates it with GitHub.
     *
     * @param string $name The name of the app.
     * @return array The created app data.
     * @throws \Exception If the app creation fails.
     */
    public function create_app_record(string $name): array {
        if (empty($name)) {
            throw new \InvalidArgumentException('App name cannot be empty.');
        }

        // Validate the app name with GitHub (example API call)
        $isValid = $this->validate_app_name_with_github($name);
        if (!$isValid) {
            throw new \Exception('The app name is invalid or already in use on GitHub.');
        }

        $appData = $this->get_connection_data();
        $newApp = [
            'id' => uniqid('app_', true),
            'name' => $name,
            'account_type' => null,
            'managed_repositories' => [],
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $apps = $appData->all();
        $apps[$newApp['id']] = $newApp;
        $appData->save($apps);

        Logger::log('INFO', 'Created new GitHub App record.', ['app' => $newApp]);

        return $newApp;
    }

    /**
     * Validates the app name with GitHub.
     *
     * @param string $name The app name to validate.
     * @return bool True if the app name is valid, false otherwise.
     */
    private function validate_app_name_with_github(string $name): bool {
        // Example validation logic (replace with actual GitHub API call)
        return !empty($name) && strlen($name) >= 3;
    }

    /**
     * Updates the credentials for an existing app.
     *
     * @param string $id The app ID.
     * @param array $credentials The new credentials.
     * @return array The updated app data.
     */
    public function update_app_credentials(string $id, array $credentials): array {
        $appData = $this->get_connection_data();
        $apps = $appData->all();

        if (!isset($apps[$id])) {
            throw new \Exception('App not found.');
        }

        $apps[$id] = array_merge($apps[$id], $credentials);
        $appData->save($apps);

        return $apps[$id];
    }

    /**
     * Clears stored credentials for a specific app.
     *
     * @param string $id The app ID.
     */
    public function clear_stored_credentials(string $id): void {
        $appData = $this->get_connection_data();
        $apps = $appData->all();

        if (isset($apps[$id])) {
            unset($apps[$id]['credentials']);
            $appData->save($apps);
        }
    }
}