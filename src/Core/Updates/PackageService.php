<?php

namespace WP2\Update\Core\Updates;

use WP2\Update\Core\API\RepositoryService;
use WP2\Update\Core\API\ReleaseService;
use WP2\Update\Core\API\GitHubClientFactory;
use WP2\Update\Utils\SharedUtils;

/**
 * Handles package-related operations.
 */
class PackageService
{
    private RepositoryService $repositoryService;
    private ReleaseService $releaseService;
    private SharedUtils $utils;
    private GitHubClientFactory $clientFactory;

    public function __construct(RepositoryService $repositoryService, ReleaseService $releaseService, SharedUtils $utils, GitHubClientFactory $clientFactory)
    {
        $this->repositoryService = $repositoryService;
        $this->releaseService = $releaseService;
        $this->utils = $utils;
        $this->clientFactory = $clientFactory;
    }

    /**
     * Synchronize packages with GitHub repositories.
     *
     * @return array
     */
    public function sync_packages(): array
    {
        $managedPackages = $this->repositoryService->get_managed_repositories();
        $syncedData = [];

        foreach ($managedPackages as $package) {
            $latestRelease = $this->releaseService->get_latest_release($package['owner'], $package['repo']);
            $syncedData[] = [
                'package' => $package,
                'latest_release' => $latestRelease,
            ];
        }

        return $syncedData;
    }

    /**
     * Manage package updates or rollbacks.
     *
     * @param string $action
     * @param string $package
     * @param string $version
     * @return bool
     */
    public function manage_packages(string $action, string $package, string $version, string $type): bool
    {
        try {
            $release = $this->releaseService->get_release_by_version($package, $version);
            if (!$release) {
                throw new \RuntimeException('Release not found.');
            }

            if ($action === 'rollback') {
                $previousVersion = $this->releaseService->get_previous_version($package, $version);
                if (!$previousVersion) {
                    throw new \RuntimeException('Previous version not found for rollback.');
                }

                $zipUrl = $this->releaseService->get_zip_url_from_release($previousVersion);
                if (!$zipUrl) {
                    throw new \RuntimeException('Download URL for rollback not found.');
                }
            } else {
                $zipUrl = $this->releaseService->get_zip_url_from_release($release);
                if (!$zipUrl) {
                    throw new \RuntimeException('Download URL not found.');
                }
            }

            $token = $this->clientFactory->getInstallationToken();
            if (!$token) {
                throw new \RuntimeException('Failed to retrieve authentication token.');
            }

            $tempFile = $this->download_package($zipUrl);
            if (!$tempFile) {
                throw new \RuntimeException('Failed to download package.');
            }

            $this->install_from_zip($tempFile, $type);

            return true;
        } catch (\Exception $e) {
            // Log the error
            return false;
        }
    }

    /**
     * Installs a package from a ZIP file.
     *
     * @param string $filePath The path to the ZIP file.
     * @throws \RuntimeException If the installation fails.
     */
    private function install_from_zip(string $filePath, string $packageType): void
    {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        if ($packageType === 'plugin') {
            $upgrader = new \Plugin_Upgrader(new \Automatic_Upgrader_Skin());
        } elseif ($packageType === 'theme') {
            $upgrader = new \Theme_Upgrader(new \Automatic_Upgrader_Skin());
        } else {
            throw new \RuntimeException('Invalid package type.');
        }

        $result = $upgrader->install($filePath);

        // Clean up the temporary file
        @unlink($filePath);

        if (is_wp_error($result)) {
            throw new \RuntimeException($result->get_error_message());
        }
    }

    /**
     * Fetches all packages managed by the plugin.
     *
     * @return array List of packages.
     */
    public function get_all_packages(): array
    {
        $repositories = $this->repositoryService->get_managed_repositories();
        $packages = [];

        foreach ($repositories as $repo) {
            $latestRelease = $this->releaseService->get_latest_release($repo['owner']['login'], $repo['name']);
            $packages[] = [
                'name' => $repo['name'],
                'owner' => $repo['owner']['login'],
                'latest_release' => $latestRelease,
            ];
        }

        return $packages;
    }

    /**
     * Downloads a package to a temporary file.
     *
     * @param string $url The package URL.
     * @return string|null The path to the downloaded file, or null on failure.
     */
    public function download_package(string $url): ?string
    {
        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $tempFile = download_url($url);

        if (is_wp_error($tempFile)) {
            $this->utils->log_error('Failed to download package: ' . $tempFile->get_error_message());
            return null;
        }

        return $tempFile;
    }
}