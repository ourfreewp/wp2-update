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
    public function get_repositories(): ?array
    {
        // Check if cached data exists
        $cache_key = Config::TRANSIENT_REPOSITORIES_CACHE;
        $cached_repositories = get_transient($cache_key);
        if ($cached_repositories !== false) {
            return $cached_repositories;
        }

        // Fetch repositories from GitHub
        $client = $this->clientFactory->getInstallationClient();
        if (!$client) {
            // Log error if needed.
            Logger::log('ERROR', 'GitHub client not initialized in get_repositories.');
            return null;
        }

        try {
            $repositories = $client->currentUser()->repositories();

            // Fetch releases for each repository
            foreach ($repositories as &$repo) {
                try {
                    $releasesApi = new Releases($client);
                    $repo['releases'] = $releasesApi->all($repo['owner']['login'], $repo['name']);
                } catch (ExceptionInterface $e) {
                    Logger::log('ERROR', 'Failed to fetch releases for repository: ' . $e->getMessage());
                    $repo['releases'] = [];
                }
            }

            // Cache the result for 1 hour
            set_transient($cache_key, $repositories, HOUR_IN_SECONDS);

            return $repositories;
        } catch (ExceptionInterface $e) {
            Logger::log('ERROR', 'Failed to fetch repositories: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get user repositories.
     *
     * @return array
     */
    public function getUserRepositories(): array
    {
        try {
            $client = $this->clientFactory->getInstallationClient();
            if (!$client) {
                throw new \RuntimeException('GitHub client not initialized.');
            }

            return $client->currentUser()->repositories();
        } catch (\Exception $e) {
            // Log error if needed.
            return [];
        }
    }

    /**
     * Fetches repositories managed by the plugin.
     *
     * @return array List of managed repositories.
     */
    public function get_managed_repositories(): array
    {
        $repositories = $this->get_repositories();
        if (!$repositories) {
            return [];
        }

        // Filter repositories based on plugin-specific criteria
        return array_filter($repositories, function ($repo) {
            return isset($repo['topics']) && in_array('wp2-managed', $repo['topics'], true);
        });
    }
}