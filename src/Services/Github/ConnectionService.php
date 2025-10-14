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
        // Placeholder implementation
        return [
            'status' => 'connected',
            'last_checked' => date('Y-m-d H:i:s'),
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
}