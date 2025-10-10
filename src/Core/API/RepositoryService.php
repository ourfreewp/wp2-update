<?php

namespace WP2\Update\Core\API;

use Github\Exception\ExceptionInterface;
use Github\Api\Repository\Releases;
use WP2\Update\Config;
use WP2\Update\Utils\Logger;

/**
 * Handles GitHub repository-related operations.
 */
class RepositoryService
{
    private GitHubClientFactory $clientFactory;

    public function __construct(GitHubClientFactory $clientFactory)
    {
        $this->clientFactory = $clientFactory;
    }

    /**
     * Fetches repositories for the authenticated GitHub App installation, including releases.
     * Caches the result using the WordPress Transients API.
     *
     * @return array|null List of repositories with releases or null on failure.
     */
    public function get_repositories(int $cache_duration = HOUR_IN_SECONDS): ?array
    {
        // Check if cached data exists
        $cache_key = Config::TRANSIENT_REPOSITORIES_CACHE;
        $cached_repositories = get_transient($cache_key);
        if ($cached_repositories !== false) {
            Logger::log('INFO', 'Returning cached repositories.');
            return $cached_repositories;
        }

        // Fetch repositories from GitHub
        $client = $this->clientFactory->getInstallationClient();
        if (!$client) {
            Logger::log('ERROR', 'GitHub client not initialized in get_repositories.');
            return null;
        }

        try {
            $repositories = $client->currentUser()->repositories();

            // Cache the result for the specified duration
            set_transient($cache_key, $repositories, $cache_duration);
            Logger::log('INFO', 'Repositories cached successfully.');

            return $repositories;
        } catch (ExceptionInterface $e) {
            Logger::log('ERROR', 'Failed to fetch repositories: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetches repositories managed by the plugin.
     *
     * @return array List of managed repositories.
     */
    public function get_managed_repositories(): array
    {
        $all_repos = $this->get_repositories();
        if (empty($all_repos)) {
            return [];
        }

        $managed_repos = array_filter($all_repos, function ($repo) {
            return !empty($repo['topics']) && in_array('wp2-managed', $repo['topics'], true);
        });

        // Now, fetch releases *only* for the managed repos
        foreach ($managed_repos as &$repo) {
            if (empty($repo['releases'])) { // Avoid re-fetching if already cached
                try {
                    $releasesApi = new \Github\Api\Repository\Releases($this->clientFactory->getInstallationClient());
                    $repo['releases'] = $releasesApi->all($repo['owner']['login'], $repo['name']);
                } catch (\Github\Exception\ExceptionInterface $e) {
                    Logger::log('ERROR', 'Failed to fetch releases for repository: ' . $e->getMessage());
                    $repo['releases'] = [];
                }
            }
        }

        return $managed_repos;
    }

    /**
     * Retrieves cached repositories or fetches them if not cached.
     *
     * @return array List of cached repositories.
     */
    public function get_cached_repositories(): array
    {
        $cacheKey = 'wp2_cached_repositories';
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $repositories = $this->get_managed_repositories();
        set_transient($cacheKey, $repositories, HOUR_IN_SECONDS);

        return $repositories;
    }

    /**
     * Retrieves cached release data for a specific repository slug.
     *
     * @param string $slug The repository slug (e.g., owner/repo).
     * @return array|null The cached release data, or null if not available.
     */
    public function get_cached_release_data(string $slug): ?array
    {
        $cacheKey = sprintf(Config::TRANSIENT_LATEST_RELEASE, ...explode('/', $slug));
        $cachedData = get_transient($cacheKey);

        if ($cachedData !== false) {
            return $cachedData;
        }

        Logger::log('INFO', "No cached release data found for slug: {$slug}");
        return null;
    }
}