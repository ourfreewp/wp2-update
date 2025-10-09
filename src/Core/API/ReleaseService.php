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
     * Provides the GitHub installation token.
     *
     * @return string|null The installation token, or null on failure.
     */
    public function getInstallationToken(): ?string
    {
        return $this->clientFactory->getInstallationToken();
    }

    /**
     * Downloads a package to a temporary file.
     *
     * @param string $url   The package URL.
     * @param string $token The GitHub installation token.
     * @return string|null  The path to the downloaded file, or null on failure.
     */
    public function download_to_temp_file(string $url, string $token): ?string
    {
        return $this->download_package($url, $token);
    }

    /**
     * Downloads a package to a temporary file.
     *
     * @param string $url   The package URL.
     * @param string $token The GitHub installation token.
     * @return string|null  The path to the downloaded file, or null on failure.
     */
    public function download_package(string $url, string $token): ?string
    {
        if (!function_exists('wp_tempnam')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $tempFile = wp_tempnam($url);
        if (!$tempFile) {
            return null;
        }

        $response = wp_remote_get(
            $url,
            [
                'timeout' => 300,
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept'        => 'application/octet-stream',
                ],
                'stream'   => true,
                'filename' => $tempFile,
            ]
        );

        if (is_wp_error($response)) {
            @unlink($tempFile);
            Logger::log('ERROR', 'Exception during file download: ' . $response->get_error_message());
            return null;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        if ($statusCode < 200 || $statusCode >= 300) {
            @unlink($tempFile);
            Logger::log('ERROR', 'Failed to download package from ' . $url . ' (HTTP ' . $statusCode . ').');
            return null;
        }

        if (!file_exists($tempFile) || 0 === filesize($tempFile)) {
            @unlink($tempFile);
            Logger::log('ERROR', 'Downloaded package was empty for ' . $url);
            return null;
        }

        return $tempFile;
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
            $parts = explode('/', $repoSlug);
            if (count($parts) !== 2) {
                throw new \InvalidArgumentException('Invalid repository slug.');
            }

            [$owner, $repo] = $parts;

            $url = sprintf('https://api.github.com/repos/%s/%s/releases/tags/%s', $owner, $repo, $version);
            $releaseData = HttpClient::get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->clientFactory->getInstallationToken(),
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
     * Retrieves the previous version of a package.
     *
     * @param string $package The package name.
     * @param string $currentVersion The current version of the package.
     * @return array|null The previous release data, or null if not found.
     */
    public function get_previous_version(string $package, string $currentVersion): ?array
    {
        $token = $this->clientFactory->getInstallationToken(); // Directly use GitHubClientFactory
        if (!$token) {
            return null;
        }

        $parts = explode('/', $package);
        if (count($parts) !== 2) {
            return null;
        }

        [$owner, $repo] = $parts;
        $url = sprintf('https://api.github.com/repos/%s/%s/releases?per_page=20', $owner, $repo);
        $releases = HttpClient::get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/vnd.github.v3+json',
            ],
        ]);

        if (!is_array($releases)) {
            return null;
        }

        $currentNormalized = ltrim((string) $currentVersion, 'v');
        $foundCurrent = false;

        foreach ($releases as $release) {
            if (!is_array($release) || !empty($release['draft'])) {
                continue;
            }

            $tag = isset($release['tag_name']) ? ltrim((string) $release['tag_name'], 'v') : '';

            if ($foundCurrent) {
                return $release;
            }

            if ($tag === $currentNormalized) {
                $foundCurrent = true;
            }
        }

        return null;
    }
}
