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

            $zipUrl = $this->releaseService->get_zip_url_from_release($release);
            if (!$zipUrl) {
                throw new \RuntimeException('Download URL not found.');
            }

            $token = $this->clientFactory->getInstallationToken();
            if (!$token) {
                throw new \RuntimeException('Failed to retrieve authentication token.');
            }

            $tempFile = $this->releaseService->download_to_temp_file($zipUrl, $token);
            if (!$tempFile) {
                throw new \RuntimeException('Failed to download the package.');
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
     * Get the status of a specific package by its repository slug.
     *
     * @param string $repoSlug The repository slug (e.g., owner/repo).
     * @return array|null The package status or null if not found.
     */
    public function get_package_status(string $repoSlug): ?array
    {
        $managedPackages = $this->repositoryService->get_managed_repositories();

        foreach ($managedPackages as $package) {
            if ($package['slug'] === $repoSlug) {
                return [
                    'name' => $package['name'],
                    'version' => $package['version'],
                    'last_updated' => $package['last_updated'],
                ];
            }
        }

        return null;
    }
}