<?php

namespace WP2\Update\Core\Updates;

use WP2\Update\Core\API\RepositoryService;
use WP2\Update\Core\API\ReleaseService;
use WP2\Update\Core\API\GitHubClientFactory;
use WP2\Update\Utils\Formatting;
use WP2\Update\Utils\Logger;

/**
 * Handles package-related operations.
 */
class PackageService
{
    private RepositoryService $repositoryService;
    private ReleaseService $releaseService;
    private GitHubClientFactory $clientFactory;
    private PackageFinder $packageFinder;

    public function __construct(RepositoryService $repositoryService, ReleaseService $releaseService, GitHubClientFactory $clientFactory, PackageFinder $packageFinder)
    {
        $this->repositoryService = $repositoryService;
        $this->releaseService = $releaseService;
        $this->clientFactory = $clientFactory;
        $this->packageFinder = $packageFinder;
    }

    /**
     * Synchronize packages with GitHub repositories.
     *
     * @return array
     */
    public function sync_packages(): array
    {
        $repositories = $this->repositoryService->get_managed_repositories();
        if (empty($repositories)) {
            return [];
        }

        foreach ($repositories as &$repository) {
            $repository['normalized_repo'] = Formatting::normalize_repo($repository['repo'] ?? '');
        }

        return $repositories;
    }

    /**
     * Manage package updates or rollbacks.
     *
     * @param string $action
     * @param string $package
     * @param string $version
     * @return bool
     */
    public function manage_packages(string $action, string $repoSlug, string $version, ?string $packageType = null): bool
    {
        try {
            $normalizedRepo = Formatting::normalize_repo($repoSlug);
            if (!$normalizedRepo) {
                throw new \Exception('Invalid repository slug provided.');
            }

            $release = $this->releaseService->get_release_by_version($normalizedRepo, $version);
            if (!$release) {
                throw new \Exception("Release '{$version}' not found for repository '{$normalizedRepo}'.");
            }

            $zipUrl = $this->releaseService->get_zip_url_from_release($release);
            if (!$zipUrl) {
                throw new \Exception('Download URL not found in release assets.');
            }

            $token = $this->clientFactory->getInstallationToken();
            if (!$token) {
                throw new \Exception('Failed to retrieve authentication token.');
            }

            $tempFile = $this->releaseService->download_package($zipUrl, $token);
            if (!$tempFile) {
                throw new \Exception('Failed to download package from GitHub.');
            }

            $type = $packageType ?: $this->get_package_type_by_repo($normalizedRepo);
            $this->install_from_zip($tempFile, $type);

            return true;
        } catch (\Exception $e) {
            Logger::log('ERROR', 'Package management failed: ' . $e->getMessage());
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
        return $this->sync_packages();
    }

    /**
     * Downloads a package to a temporary file.
     *
     * @param string $url   The package URL.
     * @param string $token The GitHub installation token.
     * @return string|null  The path to the downloaded file, or null on failure.
     */
    private function download_package(string $url, string $token): ?string
    {
        return $this->releaseService->download_package($url, $token);
    }

    public function get_package_status(string $repoSlug): ?array
    {
        $repoSlug = Formatting::normalize_repo($repoSlug) ?? '';
        if ('' === $repoSlug) {
            return null;
        }

        foreach ($this->sync_packages() as $package) {
            if (($package['repo'] ?? '') === $repoSlug) {
                return $package;
            }
        }

        return null;
    }

    private function normalize_repository(array $repository): array
    {
        $owner = '';
        if (!empty($repository['owner']['login'])) {
            $owner = (string) $repository['owner']['login'];
        } elseif (!empty($repository['owner'])) {
            $owner = (string) $repository['owner'];
        }

        $name = isset($repository['name']) ? (string) $repository['name'] : '';
        $fullName = isset($repository['full_name']) ? (string) $repository['full_name'] : '';

        if ('' === $fullName && '' !== $owner && '' !== $name) {
            $fullName = $owner . '/' . $name;
        }

        return [$owner, $name, $fullName];
    }

    private function infer_type_from_topics(array $topics): ?string
    {
        if (in_array('wordpress-theme', $topics, true)) {
            return 'theme';
        }

        if (in_array('wordpress-plugin', $topics, true)) {
            return 'plugin';
        }

        return null;
    }

    private function resolve_installed_package(string $repoSlug): ?array
    {
        $normalized = Formatting::normalize_repo($repoSlug);
        if (!$normalized) {
            return null;
        }

        foreach ($this->packageFinder->get_managed_packages() as $package) {
            if (($package['repo'] ?? '') === $normalized) {
                return $package;
            }
        }

        return null;
    }

    private function get_package_type_by_repo(string $repoSlug): ?string
    {
        $packages = $this->packageFinder->get_managed_packages();
        foreach ($packages as $package) {
            if ($package['repo'] === $repoSlug) {
                return $package['type'];
            }
        }
        return null;
    }
}
