<?php

namespace WP2\Update\Services\Github;

use WP2\Update\Data\AppData;
use WP2\Update\Utils\Cache;
use WP2\Update\Utils\Logger;
use WP2\Update\Config;

/**
 * Handles operations related to GitHub repositories.
 */
class RepositoryService {
    private AppData $appData;
    private ClientService $clientService;

    public function __construct(AppData $appData, ClientService $clientService) {
        $this->appData = $appData;
        $this->clientService = $clientService;
    }

    /**
     * Fetches all repositories accessible by a specific GitHub App installation.
     * @param string|null $app_id The app context.
     * @return array List of repositories.
     */
    public function get_all_repositories(?string $app_id = null): array {
        $app_id = $this->appData->resolve_app_id($app_id);
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
        $app_id = $this->appData->resolve_app_id($app_id);
        if (!$app_id) return [];

        $app = $this->appData->find($app_id);
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

    /**
     * Creates a new GitHub repository under the specified app context.
     *
     * @param string $repoName The name of the repository to create.
     * @param string $appId The app context under which the repository will be created.
     * @return array Details of the created repository.
     */
    public function create_repository(string $repoName, string $appId): array {
        Logger::log('INFO', "Creating repository: {$repoName} under app: {$appId}");

        try {
            $client = $this->clientService->getInstallationClient($appId);
            if (!$client) {
                throw new \RuntimeException("Failed to retrieve GitHub client for app: {$appId}");
            }

            $response = $client->repositories()->create(
                $repoName, // Repo Name
                '', // Repo Description
                // string $homepage = '',
                // bool $public = true,
                // string|null $organization = null,
                // bool $hasIssues = false,
                // bool $hasWiki = false,
                // bool $hasDownloads = false,
                // int $teamId = null,
                // bool $autoInit = false,
                // bool $hasProjects = true,
                // string|null $visibility = null
            );

            Logger::log('INFO', "Repository created successfully: {$repoName}");

            return $response;
        } catch (\Throwable $exception) {
            Logger::log('ERROR', "Failed to create repository: {$repoName}. Error: " . $exception->getMessage());
            throw $exception;
        }
    }
}

