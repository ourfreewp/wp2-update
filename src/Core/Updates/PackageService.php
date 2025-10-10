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
        $localPackages = $this->packageFinder->get_managed_packages();
        $githubRepos = $this->repositoryService->get_managed_repositories();

        $repoIndex = [];
        foreach ($githubRepos as $repo) {
            // Use 'full_name' as the key, which is the "owner/repo" slug.
            $repoIndex[$repo['full_name']] = $repo;
        }

        $packages = [];
        $unlinkedPackages = [];
        foreach ($localPackages as $localPackage) {
            // The 'repo' key from PackageFinder holds the "owner/repo" slug.
            $repoSlug = $localPackage['repo'];
            $githubData = $repoIndex[$repoSlug] ?? null;

            if ($githubData) {
                $releases = $this->releaseService->get_releases($repoSlug);
                $packages[] = array_merge(
                    $localPackage,
                    [
                        'github_data' => $githubData,
                        'last_updated' => $githubData['updated_at'] ?? null,
                        'stars' => $githubData['stargazers_count'] ?? 0,
                        'issues' => $githubData['open_issues_count'] ?? 0,
                        'releases' => array_map(function ($release) {
                            return [
                                'tag' => $release['tag_name'] ?? '',
                                'label' => $release['name'] ?? '',
                                'download_url' => $release['zipball_url'] ?? '',
                            ];
                        }, $releases),
                    ]
                );
            } else {
                $unlinkedPackages[] = $localPackage;
            }
        }

        return [
            'packages' => $packages,
            'unlinked_packages' => $unlinkedPackages,
        ];
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

            // Validate the downloaded package
            if (!$this->is_valid_package_archive($tempFile, $repoSlug)) {
                @unlink($tempFile);
                throw new \Exception("The downloaded file for '{$repoSlug}' is not a valid package.");
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

    /**
     * Verifies the contents of a downloaded package ZIP file.
     *
     * @param string $filePath Path to the temporary zip file.
     * @param string $repoSlug The expected repository slug (e.g., 'owner/repo').
     * @return bool True if the package is valid, false otherwise.
     */
    private function is_valid_package_archive(string $filePath, string $repoSlug): bool
    {
        $tempDir = wp_tempnam('wp2_package_validation');
        if (!$tempDir) {
            Logger::log('ERROR', 'Failed to create temporary directory for package validation.');
            return false;
        }

        // Remove the temp file and create a directory instead
        unlink($tempDir);
        if (!wp_mkdir_p($tempDir)) {
            Logger::log('ERROR', 'Failed to create directory for package validation: ' . $tempDir);
            return false;
        }

        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            Logger::log('ERROR', 'Failed to open ZIP file: ' . $filePath);
            $this->delete_directory($tempDir);
            return false;
        }

        // Extract the archive to the temporary directory
        if (!$zip->extractTo($tempDir)) {
            Logger::log('ERROR', 'Failed to extract ZIP file: ' . $filePath);
            $zip->close();
            $this->delete_directory($tempDir);
            return false;
        }
        $zip->close();

        $isValid = false;

        // Check for key files based on package type
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tempDir));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $filePath = $file->getPathname();

                // Check for `style.css` for themes
                if (basename($filePath) === 'style.css') {
                    $isValid = true;
                    break;
                }

                // Check for PHP files with `Plugin Name:` header for plugins
                if (pathinfo($filePath, PATHINFO_EXTENSION) === 'php') {
                    $contents = file_get_contents($filePath);
                    if (strpos($contents, 'Plugin Name:') !== false) {
                        $isValid = true;
                        break;
                    }
                }
            }
        }

        // Validate the integrity of the archive using a checksum (if available)
        $expectedChecksum = $this->repositoryService->get_package_checksum($repoSlug);
        if ($expectedChecksum && hash_file('sha256', $filePath) !== $expectedChecksum) {
            Logger::log('ERROR', 'Checksum validation failed for package: ' . $repoSlug);
            $this->delete_directory($tempDir);
            return false;
        }

        // Validate file types and extensions
        $allowedExtensions = ['php', 'css', 'js', 'json', 'txt'];
        foreach ($iterator as $file) {
            if ($file->isFile() && !in_array($file->getExtension(), $allowedExtensions, true)) {
                Logger::log('ERROR', 'Invalid file type found in package: ' . $file->getFilename());
                $this->delete_directory($tempDir);
                return false;
            }

            // Prevent directory traversal
            if (strpos(realpath($file->getPathname()), realpath($tempDir)) !== 0) {
                Logger::log('ERROR', 'Directory traversal attempt detected in package: ' . $file->getFilename());
                $this->delete_directory($tempDir);
                return false;
            }
        }

        // Clean up the temporary directory
        $this->delete_directory($tempDir);

        return $isValid;
    }

    /**
     * Recursively delete a directory and its contents.
     *
     * @param string $dirPath Path to the directory to delete.
     */
    private function delete_directory(string $dirPath): void
    {
        if (!is_dir($dirPath)) {
            return;
        }

        $files = array_diff(scandir($dirPath), ['.', '..']);
        foreach ($files as $file) {
            $filePath = $dirPath . DIRECTORY_SEPARATOR . $file;
            if (is_dir($filePath)) {
                $this->delete_directory($filePath);
            } else {
                unlink($filePath);
            }
        }

        rmdir($dirPath);
    }
}
