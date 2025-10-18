<?php
declare(strict_types=1);
namespace WP2\Update\Services\Github;

defined('ABSPATH') || exit;
use WP2\Update\Utils\Cache;
use WP2\Update\Config;
use WP2\Update\Data\AppData;
use WP2\Update\Utils\Logger;

/**
 * Class ReleaseService
 *
 * Handles all interactions with the GitHub Releases API.
 */
class ReleaseService
{
    /**
     * @var ClientService Handles interactions with the GitHub API client.
     */
    private ClientService $clientService;

    /**
     * @var AppData Provides access to app-related data.
     */
    private AppData $appData;

    /**
     * Constructor for ReleaseService.
     *
     * @param ClientService $clientService Handles interactions with the GitHub API client.
     * @param AppData $appData Provides access to app-related data.
     */
    public function __construct(ClientService $clientService, AppData $appData)
    {
        $this->clientService = $clientService;
        $this->appData = $appData;
    }
    
    /**
     * Fetches the latest release for a repository.
     *
     * @param string $repo_slug The repository slug (e.g., 'owner/repo').
     * @param string|null $app_id The app context.
     * @return array|null The latest release data or null on failure.
     */
    public function get_latest_release(string $repo_slug, ?string $app_id = null): ?array
    {
        Logger::info('Fetching latest release.', ['repo_slug' => $repo_slug, 'app_id' => $app_id]);

        [$owner, $repo] = explode('/', $repo_slug);
        $cache_key = sprintf(Config::TRANSIENT_LATEST_RELEASE, $owner, $repo);
        $cached = Cache::get($cache_key);

        if ($cached !== false) {
            Logger::info('Using cached latest release.', ['repo_slug' => $repo_slug]);
            return $cached;
        }

        try {
            $app_id = $app_id ?? $this->appData->find_active_app()['id'] ?? null;

            $client = $this->clientService->getInstallationClient($app_id);
            if (!$client) {
                Logger::warning('No GitHub client available.', ['app_id' => $app_id]);
                return null;
            }

            $release = $client->repository()->releases()->latest($owner, $repo);
            Cache::set($cache_key, $release, HOUR_IN_SECONDS);
            Logger::info('Latest release fetched and cached.', ['repo_slug' => $repo_slug]);
            return $release;
        } catch (\Throwable $e) {
            Logger::error('Error fetching latest release.', ['exception' => $e->getMessage(), 'repo_slug' => $repo_slug]);
            return null;
        }
    }

    /**
     * Fetch the latest release by channel preference.
     *
     * @param string $repo_slug The repository slug (e.g., "owner/repo").
     * @param string $channel The release channel (e.g., "stable", "beta").
     * @param string|null $app_id Optional app ID for authentication.
     * @return array|null The latest release data or null if not found.
     */
    public function get_latest_release_by_channel(string $repo_slug, string $channel, ?string $app_id = null): ?array {
        $channel = strtolower($channel);
        $all = $this->get_all_releases($repo_slug, $app_id);
        if (empty($all)) {
            return null;
        }

        if ($channel === 'stable') {
            foreach ($all as $rel) {
                if (!($rel['draft'] ?? false) && !($rel['prerelease'] ?? false)) {
                    return $rel;
                }
            }
            return null;
        }

        foreach ($all as $rel) {
            if (!($rel['draft'] ?? false) && ($rel['prerelease'] ?? false)) {
                return $rel;
            }
        }

        return $this->get_latest_release($repo_slug, $app_id);
    }

    /**
     * Fetches a specific release by its version tag.
     */
    public function get_release_by_version(string $repo_slug, string $version, ?string $app_id = null): ?array
    {
        Logger::info('Fetching release by version.', ['repo_slug' => $repo_slug, 'version' => $version, 'app_id' => $app_id]);

        // Validate repo_slug format
        if (!preg_match('/^[a-zA-Z0-9_.-]+\/[a-zA-Z0-9_.-]+$/', $repo_slug)) {
            throw new \InvalidArgumentException(__('Invalid repository slug.', Config::TEXT_DOMAIN));
        }

        // Validate version format
        if (empty($version)) {
            throw new \InvalidArgumentException(__('Version cannot be empty.', Config::TEXT_DOMAIN));
        }

        [$owner, $repo] = explode('/', $repo_slug);
        $cache_key = sprintf('%s_%s_%s_%s', Config::TRANSIENT_LATEST_RELEASE, $owner, $repo, $version);
        $cached = Cache::get($cache_key);

        if ($cached !== false) {
            Logger::info('Using cached release by version.', ['repo_slug' => $repo_slug, 'version' => $version]);
            return $cached;
        }

        try {
            $app_id = $this->appData->resolve_app_id($app_id);

            if (!$app_id) {
                Logger::warning('No app ID resolved.', ['repo_slug' => $repo_slug, 'version' => $version]);
                return null;
            }

            $client = $this->clientService->getInstallationClient($app_id);
            if (!$client) {
                Logger::warning('No GitHub client available.', ['app_id' => $app_id]);
                return null;
            }

            $release = $client->repository()->releases()->tag($owner, $repo, $version);
            Cache::set($cache_key, $release, HOUR_IN_SECONDS);
            Logger::info('Release by version fetched and cached.', ['repo_slug' => $repo_slug, 'version' => $version]);
            return $release;
        } catch (\Github\Exception\RuntimeException $e) {
            Logger::warning('GitHub API error while fetching release.', ['exception' => $e->getMessage(), 'repo_slug' => $repo_slug, 'version' => $version]);
            return null;
        } catch (\Throwable $e) {
            Logger::error('Unexpected error while fetching release.', ['exception' => $e->getMessage(), 'repo_slug' => $repo_slug, 'version' => $version]);
            return null;
        }
    }

    /**
     * Fetches all releases for a repository.
     */
    public function get_all_releases(string $repo_slug, ?string $app_id = null): array
    {
        [$owner, $repo] = explode('/', $repo_slug);
        $cache_key = sprintf(Config::TRANSIENT_ALL_RELEASES, $owner, $repo);
        $cached = Cache::get($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        try {
            $client = $this->clientService->getInstallationClient($app_id);
            if (!$client) {
                return [];
            }
            $releases = $client->repository()->releases()->all($owner, $repo);
            Cache::set($cache_key, $releases, HOUR_IN_SECONDS);
            return $releases;
        } catch (\Throwable $e) {
            Logger::error('Error fetching all releases.', ['exception' => $e->getMessage(), 'repo_slug' => $repo_slug]);
            return [];
        }
    }

    /**
     * Downloads a package zip file from a given URL.
     */
    public function download_package(string $url, ?string $app_id = null): ?string
    {
        $token = $this->clientService->getInstallationToken($app_id);
        if (!$token) {
            return null;
        }

        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        // The download_url function in WordPress doesn't support passing authorization headers directly.
        // We must use a workaround with the http_request_args filter.
        $download_args_filter = function ($args, $request_url) use ($token) {
            // Only add the Authorization header for GitHub URLs
            if (strpos($request_url, 'https://api.github.com') === 0) {
                $args['headers']['Authorization'] = 'Bearer ' . $token;
            }
            return $args;
        };
        add_filter('http_request_args', $download_args_filter, 10, 2);

        $temp_file = download_url($url);

        remove_filter('http_request_args', $download_args_filter, 10);

        if (is_wp_error($temp_file)) {
            Logger::error('Error downloading package.', ['url' => $url, 'app_id' => $app_id, 'error' => $temp_file->get_error_message()]);
            return null;
        }

        return $temp_file;
    }

    /**
     * Downloads a package ZIP file for a given repository.
     *
     * @param string $repo_slug The repository slug (e.g., 'owner/repo').
     * @return string|null Path to the downloaded ZIP file or null on failure.
     */
    public function download_package_by_repo(string $repo_slug): ?string {
        [$owner, $repo] = explode('/', $repo_slug);

        $latestRelease = $this->get_latest_release($repo_slug);
        if (!$latestRelease || empty($latestRelease['zipball_url'])) {
            return null;
        }

        $zipUrl = $latestRelease['zipball_url'];
        $tempFile = sys_get_temp_dir() . '/' . uniqid('wp2_update_', true) . '.zip';

        $downloadedFile = $this->download_package($zipUrl, null);
        if (!$downloadedFile) {
            return null;
        }

        rename($downloadedFile, $tempFile);
        return $tempFile;
    }

    /**
     * Fetches release notes for a specific repository.
     *
     * @param string $repo_slug The repository slug (e.g., 'owner/repo').
     * @return array The release notes data.
     */
    public function get_release_notes(string $repo_slug, ?string $app_id = null): array
    {
        [$owner, $repo] = explode('/', $repo_slug);

        try {
            $client = $this->clientService->getInstallationClient($app_id);
            if (!$client) {
                throw new \RuntimeException(__("Failed to authenticate with GitHub.", Config::TEXT_DOMAIN));
            }

            $releases = $client->repository()->releases()->all($owner, $repo);
            $release_notes = [];

            foreach ($releases as $release) {
                $release_notes[] = [
                    'version' => $release['tag_name'],
                    'notes' => $release['body'] ?? '',
                    'published_at' => $release['published_at'] ?? '',
                ];
            }

            return $release_notes;
        } catch (\Throwable $e) {
            Logger::error('Error fetching release notes.', ['exception' => $e->getMessage(), 'repo_slug' => $repo_slug]);
            throw new \RuntimeException(__("Failed to fetch release notes.", Config::TEXT_DOMAIN));
        }
    }

    /**
     * Retrieves the zipball URL from a release.
     *
     * @param array $release The release data.
     * @return string The zipball URL or an empty string if not available.
     */
    public function get_zip_url_from_release(array $release): string
    {
        return $release['zipball_url'] ?? '';
    }

    /**
     * Fetches all releases for a repository.
     * @param string $repo_slug The repository slug (e.g., 'owner/repo').
     * @return array The list of releases.
     */
    public function get_releases_for_package(string $repo_slug, ?string $app_id = null): array
    {
        [$owner, $repo] = explode('/', $repo_slug);
        $cache_key = sprintf(Config::TRANSIENT_ALL_RELEASES, $owner, $repo);
        $cached = Cache::get($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        try {
            $app_id = $this->appData->resolve_app_id($app_id);

            if (!$app_id) {
                Logger::error('Failed to resolve app_id for fetching releases.', ['repo_slug' => $repo_slug]);
                return [];
            }

            $client = $this->clientService->getInstallationClient($app_id);
            if (!$client) {
                return [];
            }

            $releases = $client->repo()->releases()->all($owner, $repo);
            Cache::set($cache_key, $releases, HOUR_IN_SECONDS);

            return $releases;
        } catch (\Throwable $e) {
            Logger::error('Error fetching releases for package.', ['exception' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Invalidate the cache for a specific repository or all repositories.
     *
     * @param string|null $repo_slug The repository slug to invalidate the cache for. If null, invalidates all caches.
     */
    public function invalidate_cache(?string $repo_slug = null): void
    {
        if ($repo_slug) {
            [$owner, $repo] = explode('/', $repo_slug);

            // Invalidate specific repository caches
            $cache_keys = [
                sprintf(Config::TRANSIENT_LATEST_RELEASE, $owner, $repo),
                sprintf(Config::TRANSIENT_ALL_RELEASES, $owner, $repo)
            ];

            foreach ($cache_keys as $cache_key) {
                Cache::delete($cache_key);
            }

            Logger::info('Cache invalidated for repository.', ['repo_slug' => $repo_slug]);
        } else {
            // Invalidate all caches by iterating over known keys
            $all_keys = Cache::get_all_keys('wp2_update');

            foreach ($all_keys as $key) {
                Cache::delete($key);
            }

            Logger::info('Cache invalidated for all repositories.');
        }
    }

    /**
     * Invalidate cache when release channel is updated.
     *
     * @param string $repo_slug The repository slug.
     */
    public function invalidate_cache_on_channel_update(string $repo_slug): void
    {
        $this->invalidate_cache($repo_slug);
        Logger::info('Cache invalidated due to release channel update.', ['repo_slug' => $repo_slug]);
    }
}
