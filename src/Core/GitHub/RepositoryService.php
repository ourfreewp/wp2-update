<?php

namespace WP2\Update\Core\GitHub;

use Github\Exception\ExceptionInterface;
use Github\Api\Repository\Releases;
use WP2\Update\Config;
use WP2\Update\Utils\Logger;
use WP2\Update\Core\AppRepository;
use function absint;

/**
 * Handles GitHub repository-related operations.
 */
class RepositoryService
{
    private ?GitHubClientFactory $clientFactory;
    private AppRepository $appRepository;

    public function __construct(AppRepository $appRepository, ?GitHubClientFactory $clientFactory = null)
    {
        $this->appRepository = $appRepository;
        $this->clientFactory = $clientFactory;
    }

    /**
     * Set the GitHubClientFactory instance.
     * @param GitHubClientFactory $clientFactory
     */
    public function setClientFactory(GitHubClientFactory $clientFactory): void
    {
        $this->clientFactory = $clientFactory;
    }

    /**
     * Fetches repositories for the authenticated GitHub App installation, including releases.
     * Caches the result using the WordPress Transients API.
     *
     * @return array|null List of repositories with releases or null on failure.
     */
    public function get_repositories(?string $appUid = null, int $cache_duration = HOUR_IN_SECONDS): ?array
    {
        if (!$this->clientFactory) {
            Logger::log('ERROR', 'GitHub client factory not initialised.');
            return null;
        }

        // Check if cached data exists
        $cache_key = Config::TRANSIENT_REPOSITORIES_CACHE . ($appUid ? '_' . $appUid : '_default');
        $cached_repositories = \WP2\Update\Utils\Cache::get($cache_key);
        if ($cached_repositories !== false) {
            Logger::log('INFO', 'Returning cached repositories.');
            return $cached_repositories;
        }

        // Fetch repositories from GitHub
        $client = $this->clientFactory->getInstallationClient($appUid);
        if (!$client) {
            Logger::log('ERROR', 'GitHub client not initialized in get_repositories.');
            return null;
        }

        try {
            $repositories = $client->currentUser()->repositories();

            // Cache the result for the specified duration
            \WP2\Update\Utils\Cache::set($cache_key, $repositories, $cache_duration);
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
    public function get_managed_repositories(?string $appUid = null): array
    {
        $apps = $appUid ? [$this->appRepository->find($appUid)] : $this->appRepository->all();
        $apps = array_values(array_filter($apps, static fn($app) => is_array($app)));

        if (!$apps || !$this->clientFactory) {
            return [];
        }

        $repositories = [];

        foreach ($apps as $app) {
            $managed = array_values(array_filter((array) ($app['managed_repositories'] ?? [])));
            if (empty($managed)) {
                continue;
            }

            $client = $this->clientFactory->getInstallationClient($app['id'] ?? null);
            if (!$client) {
                continue;
            }

            try {
                $response = $client->apps()->listRepositories();
            } catch (ExceptionInterface $e) {
                Logger::log('ERROR', 'Failed to fetch repositories: ' . $e->getMessage());
                continue;
            }

            $fetched = $response['repositories'] ?? $response;
            if (!is_array($fetched)) {
                continue;
            }

            foreach ($fetched as $repo) {
                if (!isset($repo['full_name']) || !in_array($repo['full_name'], $managed, true)) {
                    continue;
                }

                $repo['app_id'] = $app['id'] ?? null;

                if (empty($repo['releases'])) {
                    try {
                        $releasesApi = new Releases($client);
                        $repo['releases'] = $releasesApi->all($repo['owner']['login'], $repo['name']);
                    } catch (ExceptionInterface $e) {
                        Logger::log('ERROR', 'Failed to fetch releases for repository: ' . $e->getMessage());
                        $repo['releases'] = [];
                    }
                }

                $repositories[] = $repo;
            }
        }

        return $repositories;
    }

    /**
     * Retrieves cached repositories or fetches them if not cached.
     *
     * @return array List of cached repositories.
     */
    public function get_cached_repositories(?string $appUid = null): array
    {
        $cacheKey = 'wp2_cached_repositories' . ($appUid ? '_' . $appUid : '_all');
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $repositories = $this->get_managed_repositories($appUid);
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

    /**
     * Retrieves the checksum for a given repository slug.
     *
     * @param string $repoSlug The repository slug (e.g., 'owner/repo').
     * @return string|null The checksum or null if not available.
     */
    public function get_package_checksum(string $repoSlug, ?string $appUid = null): ?string
    {
        $repositories = $this->get_managed_repositories($appUid);
        foreach ($repositories as $repo) {
            if ($repo['full_name'] === $repoSlug && !empty($repo['checksum'])) {
                return $repo['checksum'];
            }
        }

        Logger::log('WARNING', 'Checksum not found for repository: ' . $repoSlug);
        return null;
    }

    /**
     * Find an app by its ID.
     *
     * @param string $appId The ID of the app.
     * @return array|null The app data or null if not found.
     */
    public function find_app(string $appId): ?array
    {
        Logger::log('INFO', "Finding app with ID {$appId}.");
        return $this->appRepository->find($appId);
    }

    /**
     * Save an app's data.
     *
     * @param array $app The app data to save.
     * @return void
     */
    public function save_app(array $app): void
    {
        Logger::log('INFO', "Saving app with ID {$app['id']}.");
        $this->appRepository->save($app);
    }

    /**
     * Fetch repositories by installation ID.
     *
     * @param int $installationId The GitHub App installation ID.
     * @return array|null List of repositories or null on failure.
     */
    public function get_repositories_by_installation(int $installationId): ?array
    {
        Logger::log('INFO', "Fetching repositories for installation ID {$installationId}.");

        $installationId = absint($installationId);
        if ($installationId <= 0 || !$this->clientFactory) {
            return null;
        }

        $matchedApps = $this->appRepository->find_by_field('installation_id', $installationId);
        $app = $matchedApps[0] ?? null;

        if (!$app) {
            Logger::log('WARNING', "No app found for installation ID {$installationId}.");
            return null;
        }

        $client = $this->clientFactory->getInstallationClient($app['id'] ?? null);
        if (!$client) {
            Logger::log('ERROR', 'GitHub client not available for installation fetch.');
            return null;
        }

        try {
            $response = $client->apps()->listRepositories();
            $repositories = $response['repositories'] ?? $response;
            return is_array($repositories) ? $repositories : null;
        } catch (ExceptionInterface $e) {
            Logger::log('ERROR', 'Failed to fetch repositories for installation: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update the managed repositories for a given app.
     *
     * @param string $appUid The unique ID of the app.
     * @param array $repositories List of repository slugs to manage.
     * @return void
     */
    public function update_managed_repositories(string $appUid, array $repositories): void
    {
        $app = $this->appRepository->find($appUid);
        if (!$app) {
            Logger::log('ERROR', "App with UID {$appUid} not found.");
            return;
        }

        $app['managed_repositories'] = $repositories;
        $this->appRepository->save($app);

        Logger::log('INFO', "Managed repositories updated for app {$appUid}.");
    }

    /**
     * Checks if the GitHub client has valid credentials.
     *
     * @return bool True if credentials are available, false otherwise.
     */
    public function has_credentials(): bool
    {
        if (!$this->clientFactory) {
            return false;
        }

        $client = $this->clientFactory->getInstallationClient();
        return $client !== null;
    }
}
