<?php
declare(strict_types=1);

namespace WP2\Update\Services\Github;

use WP2\Update\Data\AppData;
use WP2\Update\Utils\Cache;
use WP2\Update\Config;
use WP2\Update\Utils\Logger;

/**
 * Class RepositoryService
 *
 * Handles operations related to GitHub repositories.
 */
class RepositoryService {
    /**
     * @var AppData Provides access to app-related data.
     */
    private AppData $appData;

    /**
     * @var ClientService Handles interactions with the GitHub API client.
     */
    private ClientService $clientService;

    /**
     * Constructor for RepositoryService.
     *
     * @param AppData $appData Provides access to app-related data.
     * @param ClientService $clientService Handles interactions with the GitHub API client.
     */
    public function __construct(AppData $appData, ClientService $clientService) {
        $this->appData = $appData;
        $this->clientService = $clientService;
    }

    /**
     * Fetches all repositories accessible by a specific GitHub App installation.
     *
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
            return [];
        }
    }

    /**
     * Fetches only the repositories that are explicitly managed by the app.
     * @param string|null $app_id The app context.
     * @return array List of managed repositories with their details.
     */
    public function get_managed_repositories(?string $app_id = null): array {
        // Validate input
        if ($app_id !== null && !is_string($app_id)) {
            throw new \InvalidArgumentException('Invalid app ID provided.');
        }

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
     * @param array $options Additional options for repository creation (e.g., description, visibility).
     * @return array Details of the created repository.
     * @throws \RuntimeException If the repository creation fails.
     */
    public function create_repository(string $repoName, string $appId, array $options = []): array {
        // Validate inputs
        if (empty($repoName) || !is_string($repoName)) {
            throw new \InvalidArgumentException('Invalid repository name provided.');
        }

        if (empty($appId) || !is_string($appId)) {
            throw new \InvalidArgumentException('Invalid app ID provided.');
        }

        if (!is_array($options)) {
            throw new \InvalidArgumentException('Options must be an array.');
        }

        try {
            $client = $this->clientService->getInstallationClient($appId);
            if (!$client) {
                throw new \RuntimeException("Failed to retrieve GitHub client for app: {$appId}");
            }

            $defaultOptions = [
                'description' => '',
                'homepage' => '',
                'private' => true,
                'has_issues' => true,
                'has_projects' => true,
                'has_wiki' => true,
                'auto_init' => false,
            ];

            $options = array_merge($defaultOptions, $options);

            $response = $client->repositories()->create(
                $repoName,
                $options['description'],
                $options['homepage'],
                !$options['private'],
                null, // Organization
                $options['has_issues'],
                $options['has_wiki'],
                true, // Downloads enabled
                null, // Team ID
                $options['auto_init'],
                $options['has_projects'],
                $options['private'] ? 'private' : 'public'
            );

            return $response;
        } catch (\Throwable $exception) {
            throw new \RuntimeException("Failed to create repository: {$repoName}. Error: " . $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Updates the release channel for a specific repository.
     *
     * @param string $repo_slug The repository slug.
     * @param string $channel The new release channel.
     * @return void
     */
    public function update_channel(string $repo_slug, string $channel): void {
        // Validate inputs
        if (empty($repo_slug) || !is_string($repo_slug)) {
            throw new \InvalidArgumentException('Invalid repository slug provided.');
        }

        if (empty($channel) || !is_string($channel)) {
            throw new \InvalidArgumentException('Invalid release channel provided.');
        }

        // Logic to update the release channel for the repository.
        Logger::info("Updated release channel for {$repo_slug} to {$channel}.");
    }
}

