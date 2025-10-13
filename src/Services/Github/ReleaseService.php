<?php

namespace WP2\Update\Services\Github;
use WP2\Update\Utils\Logger;
use WP2\Update\Utils\HttpClient;
use WP2\Update\Utils\Cache;
use WP2\Update\Config;

/**
 * Handles all interactions with the GitHub Releases API.
 */
class ReleaseService
{
    private ClientService $clientService;

    public function __construct(ClientService $clientService)
    {
        $this->clientService = $clientService;
    }

    /**
     * Fetches the latest release for a repository.
     * @param string $repo_slug The repository slug (e.g., 'owner/repo').
     * @param string|null $app_id The app context.
     * @return array|null The latest release data or null on failure.
     */
    public function get_latest_release(string $repo_slug, ?string $app_id = null): ?array
    {
        [$owner, $repo] = explode('/', $repo_slug);
        $cache_key = sprintf(Config::TRANSIENT_LATEST_RELEASE, $owner, $repo);
        $cached = Cache::get($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        try {
            $client = $this->clientService->getInstallationClient($app_id);
            if (!$client) {
                return null;
            }
            $release = $client->repository()->releases()->latest($owner, $repo);
            Cache::set($cache_key, $release, HOUR_IN_SECONDS);
            return $release;
        } catch (\Exception $e) {
            Logger::log('ERROR', "Failed to fetch latest release for {$repo_slug}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetches a specific release by its version tag.
     */
    public function get_release_by_version(string $repo_slug, string $version, ?string $app_id = null): ?array
    {
        // GitHub API doesn't have a direct "get by version" endpoint, so we fetch all and find the match.
        $releases = $this->get_all_releases($repo_slug, $app_id);
        foreach ($releases as $release) {
            if ($release['tag_name'] === $version) {
                return $release;
            }
        }
        return null;
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
        } catch (\Exception $e) {
            Logger::log('ERROR', "Failed to fetch all releases for {$repo_slug}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Downloads a package zip file from a given URL.
     */
    public function download_package(string $url, ?string $app_id = null): ?string
    {
        $token = $this->clientService->getInstallationToken($this->clientService->getConnectionService()->resolve_app_id($app_id));
        if (!$token) {
            Logger::log('ERROR', 'Cannot download package without an authentication token.');
            return null;
        }

        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        // The download_url function in WordPress doesn't support passing authorization headers directly.
        // We must use a workaround with the http_request_args filter.
        $download_args_filter = function ($args) use ($token) {
            $args['headers']['Authorization'] = 'Bearer ' . $token;
            return $args;
        };
        add_filter('http_request_args', $download_args_filter, 10, 1);

        $temp_file = download_url($url);

        remove_filter('http_request_args', $download_args_filter);

        if (is_wp_error($temp_file)) {
            Logger::log('ERROR', 'Failed to download package: ' . $temp_file->get_error_message());
            return null;
        }

        return $temp_file;
    }

    /**
     * Fetches release notes for a specific repository.
     *
     * @param string $repo_slug The repository slug (e.g., 'owner/repo').
     * @return array The release notes data.
     */
    public function get_release_notes(string $repo_slug): array
    {
        [$owner, $repo] = explode('/', $repo_slug);

        try {
            $client = $this->clientService->getInstallationClient();
            if (!$client) {
                throw new \RuntimeException(__("Failed to authenticate with GitHub.", \WP2\Update\Config::TEXT_DOMAIN));
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
        } catch (\Exception $e) {
            Logger::log('ERROR', "Failed to fetch release notes for {$repo_slug}: " . $e->getMessage());
            throw new \RuntimeException(__("Failed to fetch release notes.", \WP2\Update\Config::TEXT_DOMAIN));
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
}
