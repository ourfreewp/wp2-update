<?php

namespace WP2\Update\Core\API;

use WP2\Update\Utils\Logger;
use WP2\Update\Utils\HttpClient;
use WP2\Update\Config;

/**
 * Handles GitHub release-related operations.
 */
class ReleaseService
{
    private GitHubClientFactory $clientFactory;

    public function __construct(GitHubClientFactory $clientFactory)
    {
        $this->clientFactory = $clientFactory;
    }

    /**
     * Fetches the latest release for a repository using direct GitHub REST API calls.
     *
     * @param string $owner The repository owner.
     * @param string $repo The repository name.
     * @return array|null The latest release data, or null on failure.
     */
    public function get_latest_release(string $owner, string $repo): ?array
    {
        $transientKey = sprintf(Config::TRANSIENT_LATEST_RELEASE, $owner, $repo);
        $cachedRelease = get_transient($transientKey);

        if ($cachedRelease !== false) {
            return $cachedRelease;
        }

        try {
            $token = $this->clientFactory->getInstallationToken();
            if (!$token) {
                throw new \RuntimeException('Failed to generate GitHub installation token.');
            }

            $url = "https://api.github.com/repos/{$owner}/{$repo}/releases/latest";
            $releaseData = HttpClient::get($url, [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Accept'        => 'application/vnd.github.v3+json',
                ],
            ]);

            if ($releaseData === null) {
                throw new \RuntimeException('Failed to fetch release data.');
            }

            set_transient($transientKey, $releaseData, HOUR_IN_SECONDS);
            return $releaseData;
        } catch (\Exception $e) {
            Logger::log('ERROR', 'Error fetching latest release: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Downloads a protected asset to a temporary file.
     */
    public function download_to_temp_file(string $url, string $token): ?string
    {
        if (!function_exists('wp_tempnam')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $tempFile = wp_tempnam($url);
        if (!$tempFile) {
            return null;
        }

        try {
            $response = HttpClient::get($url, [
                'timeout' => 300, // Increase timeout for large files
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

            if ($response === null) {
                @unlink($tempFile);
                Logger::log('ERROR', 'Failed to download package from ' . $url);
                return null;
            }

            file_put_contents($tempFile, $response);
            return $tempFile;
        } catch (\Throwable $e) {
            @unlink($tempFile);
            Logger::log('ERROR', 'Exception during file download: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch a specific release by version from GitHub.
     *
     * @param string $repoSlug The repository slug (e.g., owner/repo).
     * @param string $version The version tag to fetch.
     * @return array|null The release data, or null on failure.
     */
    public function get_release_by_version(string $repoSlug, string $version): ?array
    {
        try {
            [$owner, $repo] = explode('/', $repoSlug);

            $url = sprintf('https://api.github.com/repos/%s/%s/releases/tags/%s', $owner, $repo, $version);
            $releaseData = HttpClient::get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getInstallationToken(),
                    'Accept'        => 'application/vnd.github.v3+json',
                ],
            ]);

            if ($releaseData === null) {
                throw new \RuntimeException('Failed to fetch release data.');
            }

            return $releaseData;
        } catch (\Exception $e) {
            Logger::log('ERROR', 'Error fetching release by version: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Locate the most appropriate download URL from a release payload.
     */
    public function get_zip_url_from_release(array $release): ?string
    {
        $assets = $release['assets'] ?? [];

        foreach ($assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $type = $asset['content_type'] ?? '';
            if (in_array($type, ['application/zip', 'application/x-zip-compressed'], true) && !empty($asset['browser_download_url'])) {
                return (string) $asset['browser_download_url'];
            }
        }

        if (!empty($release['zipball_url'])) {
            return (string) $release['zipball_url'];
        }

        return null;
    }

    /**
     * Provides access to the GitHubClientFactory's installation token.
     *
     * @return string|null The installation token, or null if unavailable.
     */
    public function getInstallationToken(): ?string
    {
        return $this->clientFactory->getInstallationToken();
    }

    /**
     * Retrieves the previous version of a package.
     *
     * @param string $package The package name.
     * @param string $currentVersion The current version of the package.
     * @return array|null The previous release data, or null if not found.
     */
    public function get_previous_version(string $package, string $currentVersion): ?array
    {
        // Logic to fetch the previous version from GitHub or other source
        // Placeholder implementation
        return null;
    }
}