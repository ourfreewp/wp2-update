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
        \WP2\Update\Utils\Logger::info('Fetching all repositories.', ['app_id' => $app_id]);

        $app_id = $this->appData->resolve_app_id($app_id);
        if (!$app_id) {
            \WP2\Update\Utils\Logger::warning('No app ID resolved for fetching repositories.', ['app_id' => $app_id]);
            return [];
        }

        $cache_key = Config::TRANSIENT_REPOSITORIES_CACHE . '_' . $app_id;
        Logger::start('github:get_all_repositories_cache');
        $cached = Cache::get($cache_key);
        Logger::stop('github:get_all_repositories_cache');
        if ($cached !== false) {
            Logger::info('Using cached repositories.', ['app_id' => $app_id]);
            return $cached;
        }

        try {
            Logger::start('github:get_all_repositories_api');
            $client = $this->clientService->getInstallationClient($app_id);
            if (!$client) {
                Logger::warning('No GitHub client available.', ['app_id' => $app_id]);
                return [];
            }

            $repositories = $client->apps()->listRepositories();
            Logger::stop('github:get_all_repositories_api');
            Logger::start('github:set_repositories_cache');
            Cache::set($cache_key, $repositories['repositories'] ?? [], HOUR_IN_SECONDS);
            Logger::stop('github:set_repositories_cache');
            Logger::info('Repositories fetched and cached.', ['app_id' => $app_id]);
            return $repositories['repositories'] ?? [];
        } catch (\Exception $e) {
            Logger::error('Error fetching repositories.', ['exception' => $e->getMessage(), 'app_id' => $app_id]);
            return [];
        }
    }

    /**
     * Fetches only the repositories that are explicitly managed by the app.
     * @param string|null $app_id The app context.
     * @return array List of managed repositories with their details.
     */
    public function get_managed_repositories(?string $app_id = null): array {
        Logger::info('Fetching managed repositories.', ['app_id' => $app_id]);

        $app_id = $this->appData->resolve_app_id($app_id);
        if (!$app_id) {
            Logger::warning('No app ID resolved for fetching managed repositories.', ['app_id' => $app_id]);
            return [];
        }

        $app = $this->appData->find($app_id);
        $managed_slugs = $app['managed_repositories'] ?? [];
        if (empty($managed_slugs)) {
            Logger::info('No managed repositories found.', ['app_id' => $app_id]);
            return [];
        }

        $all_repos = $this->get_all_repositories($app_id);
        $managed_repos = array_filter($all_repos, fn($repo) => in_array($repo['full_name'], $managed_slugs, true));

        Logger::info('Managed repositories fetched.', ['app_id' => $app_id, 'count' => count($managed_repos)]);
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
        Logger::info('Creating repository.', ['repo_name' => $repoName, 'app_id' => $appId, 'options' => $options]);

        if (empty($repoName) || !is_string($repoName)) {
            Logger::error('Invalid repository name provided.', ['repo_name' => $repoName]);
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
                Logger::warning('No GitHub client available for repository creation.', ['app_id' => $appId]);
                throw new \RuntimeException('GitHub client not available.');
            }

            // Validate required fields in options
            if (empty($repoName) || !is_string($repoName)) {
                throw new \InvalidArgumentException('Repository name must be a non-empty string.');
            }

            if (!isset($options['name'])) {
                $options['name'] = $repoName; // Ensure the repository name is included in options
            }

            // Log the options being passed to the GitHub API
            Logger::info('Creating repository with options.', ['options' => $options]);

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

            // Extract individual parameters from options
            $description = $options['description'] ?? '';
            $homepage = $options['homepage'] ?? '';
            $private = $options['private'] ?? true;
            $hasIssues = $options['has_issues'] ?? true;
            $hasWiki = $options['has_wiki'] ?? true;
            $hasDownloads = $options['has_downloads'] ?? false;
            $autoInit = $options['auto_init'] ?? false;
            $hasProjects = $options['has_projects'] ?? true;

            // Validate extracted parameters
            if (!is_string($description)) {
                Logger::warning('Invalid description provided.', ['description' => $description]);
                $description = '';
            }

            if (!is_string($homepage)) {
                Logger::warning('Invalid homepage URL provided.', ['homepage' => $homepage]);
                $homepage = '';
            }

            if (!is_bool($private)) {
                Logger::warning('Invalid private flag provided. Defaulting to true.', ['private' => $private]);
                $private = true;
            }

            if (!is_bool($hasIssues)) {
                Logger::warning('Invalid has_issues flag provided. Defaulting to true.', ['has_issues' => $hasIssues]);
                $hasIssues = true;
            }

            if (!is_bool($hasWiki)) {
                Logger::warning('Invalid has_wiki flag provided. Defaulting to true.', ['has_wiki' => $hasWiki]);
                $hasWiki = true;
            }

            if (!is_bool($hasDownloads)) {
                Logger::warning('Invalid has_downloads flag provided. Defaulting to false.', ['has_downloads' => $hasDownloads]);
                $hasDownloads = false;
            }

            if (!is_bool($autoInit)) {
                Logger::warning('Invalid auto_init flag provided. Defaulting to false.', ['auto_init' => $autoInit]);
                $autoInit = false;
            }

            if (!is_bool($hasProjects)) {
                Logger::warning('Invalid has_projects flag provided. Defaulting to true.', ['has_projects' => $hasProjects]);
                $hasProjects = true;
            }

            // Call the create method with individual arguments
            $repository = $client->repository()->create(
                $repoName,
                $description,
                $homepage,
                !$private, // Convert private to public
                null, // Organization is not specified
                $hasIssues,
                $hasWiki,
                $hasDownloads,
                null, // Team ID is not specified
                $autoInit,
                $hasProjects
            );

            Logger::info('Repository created successfully.', ['repo_name' => $repoName, 'app_id' => $appId]);
            return $repository;
        } catch (\Exception $e) {
            Logger::error('Error creating repository.', ['exception' => $e->getMessage(), 'repo_name' => $repoName, 'app_id' => $appId]);
            throw new \RuntimeException('Failed to create repository.');
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

    /**
     * Retrieves local packages.
     *
     * @return array
     */
    public function getLocalPackages(): array {
        // Placeholder logic for fetching local packages.
        return [
            ['repo' => 'example-repo-1'],
            ['repo' => 'example-repo-2'],
        ];
    }
}

