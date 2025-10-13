<?php

namespace WP2\Update\Services\Github;

use WP2\Update\Data\ConnectionData;
use WP2\Update\Utils\Cache;
use WP2\Update\Utils\Logger;
use WP2\Update\Config;

/**
 * Handles operations related to GitHub repositories.
 */
class RepositoryService {
    private ConnectionData $connectionData;
    private ClientService $clientService;

    public function __construct(ConnectionData $connectionData, ClientService $clientService) {
        $this->connectionData = $connectionData;
        $this->clientService = $clientService;
    }

    /**
     * Fetches all repositories accessible by a specific GitHub App installation.
     * @param string|null $app_id The app context.
     * @return array List of repositories.
     */
    public function get_all_repositories(?string $app_id = null): array {
        $app_id = $this->connectionData->resolve_app_id($app_id);
        if (!$app_id) {
            return [];
        }

        $cache_key = Config::TRANSIENT_REPOSITORIES_CACHE . '_' . $app_id;
        $cached = Cache::get($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        try {
            $client = $this->clientService->getInstallationClient($app_id);
            if (!$client) {
                return [];
            }

            $repositories = $client->apps()->listRepositories();
            Cache::set($cache_key, $repositories['repositories'] ?? [], HOUR_IN_SECONDS);
            return $repositories['repositories'] ?? [];
        } catch (\Exception $e) {
            Logger::log('ERROR', 'Failed to fetch repositories for app ' . $app_id . ': ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetches only the repositories that are explicitly managed by the app.
     * @param string|null $app_id The app context.
     * @return array List of managed repositories with their details.
     */
    public function get_managed_repositories(?string $app_id = null): array {
        $app_id = $this->connectionData->resolve_app_id($app_id);
        if (!$app_id) return [];

        $app = $this->connectionData->find($app_id);
        $managed_slugs = $app['managed_repositories'] ?? [];
        if (empty($managed_slugs)) return [];

        $all_repos = $this->get_all_repositories($app_id);
        $managed_repos = [];

        foreach ($all_repos as $repo) {
            if (in_array($repo['full_name'], $managed_slugs, true)) {
                $managed_repos[] = $repo;
            }
        }

        return $managed_repos;
    }
}
