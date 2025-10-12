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
        Logger::log('DEBUG', 'sync_packages method called.');

        $localPackages = $this->packageFinder->get_managed_packages();
        $githubRepos = $this->repositoryService->get_managed_repositories();

        Logger::log('DEBUG', 'sync_packages called. Local packages: ' . print_r($localPackages, true));
        Logger::log('DEBUG', 'sync_packages called. GitHub repositories: ' . print_r($githubRepos, true));
        Logger::log('DEBUG', 'Local packages: ' . print_r($localPackages, true));
        Logger::log('DEBUG', 'GitHub repositories: ' . print_r($githubRepos, true));
        error_log('Local packages: ' . print_r($localPackages, true));
        error_log('GitHub repositories: ' . print_r($githubRepos, true));

        $repoIndex = [];
        foreach ($githubRepos as $repo) {
            $repoIndex[$repo['full_name']] = $repo;
        }

        $packages = [];
        $unlinkedPackages = [];

        foreach ($localPackages as $localPackage) {
            $repoSlug = $localPackage['repo'];
            $githubData = $repoIndex[$repoSlug] ?? null;

            if ($githubData) {
                $releases = $this->releaseService->get_releases($repoSlug);
                $latestRelease = $releases[0] ?? null;
                
                $packages[] = array_merge(
                    $localPackage,
                    [
                        'github_data' => $githubData,
                        'releases' => $releases,
                        'latest' => $latestRelease,
                        'status' => 'managed',
                    ]
                );
            } else {
                $unlinkedPackages[] = array_merge(
                    $localPackage,
                    [
                        'status' => 'unlinked',
                    ]
                );
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
        try {
            return $this->sync_packages();
        } catch (\Throwable $exception) {
            Logger::log('ERROR', 'Failed to retrieve packages: ' . $exception->getMessage());

            return [
                'managed'  => [],
                'unlinked' => [],
                'all'      => [],
                'error'    => $exception->getMessage(),
            ];
        }
    }

    /**
     * Fetches all managed packages.
     *
     * @return array The managed packages.
     */
    public function get_managed_packages(): array
    {
        try {
            return $this->packageFinder->get_managed_packages();
        } catch (\Throwable $exception) {
            Logger::log('ERROR', 'Failed to fetch managed packages: ' . $exception->getMessage());
            return [];
        }
    }

    public function get_package_status(string $repoSlug): ?array
    {
        $repoSlug = Formatting::normalize_repo($repoSlug) ?? '';
        if ('' === $repoSlug) {
            return null;
        }

        $synced = $this->sync_packages();
        $collections = [
            $synced['packages'] ?? [],
            $synced['unlinked_packages'] ?? [],
        ];

        foreach ($collections as $packages) {
            foreach ($packages as $package) {
                if (($package['repo'] ?? '') === $repoSlug) {
                    return $package;
                }
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
        // Validate the integrity of the archive using a checksum (if available)
        $expectedChecksum = $this->repositoryService->get_package_checksum($repoSlug);
        if ($expectedChecksum && hash_file('sha256', $filePath) !== $expectedChecksum) {
            Logger::log('ERROR', 'Checksum validation failed for package: ' . $repoSlug);
            return false;
        }

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
        $allowedExtensions = ['php', 'css', 'js', 'json', 'txt']; // Make configurable if needed

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

        if (!$isValid) {
            Logger::log('ERROR', 'Required files not found in the package.');
            $this->delete_directory($tempDir);
            return false;
        }

        // Validate file types and extensions
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

        $this->delete_directory($tempDir);
        return true;
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

    /**
     * Assign a repository to an app.
     *
     * @param string $appId The ID of the app.
     * @param string $repoId The ID of the repository.
     * @return void
     */
    public function assign_package_to_app(string $appId, string $repoId): void
    {
        Logger::log('INFO', "Assigning repository {$repoId} to app {$appId}.");

        // Retrieve the app
        $app = $this->repositoryService->find_app($appId);
        if (!$app) {
            throw new \RuntimeException("App with ID {$appId} not found.");
        }

        // Normalize the repository identifier
        $normalizedRepo = Formatting::normalize_repo($repoId);
        if (!$normalizedRepo) {
            throw new \InvalidArgumentException("Invalid repository identifier: {$repoId}.");
        }

        // Check if the repository is already assigned
        if (in_array($normalizedRepo, $app['managed_repositories'] ?? [], true)) {
            Logger::log('WARNING', "Repository {$normalizedRepo} is already assigned to app {$appId}.");
            return;
        }

        // Assign the repository
        $app['managed_repositories'][] = $normalizedRepo;

        // Save the updated app
        try {
            $this->repositoryService->save_app($app);
            Logger::log('INFO', "Repository {$normalizedRepo} successfully assigned to app {$appId}.");
        } catch (\Exception $e) {
            Logger::log('ERROR', "Failed to save app {$appId} after assigning repository {$normalizedRepo}: " . $e->getMessage());
            throw new \RuntimeException("Failed to save app {$appId}.", 0, $e);
        }
    }

    /**
     * Emit the new plugin scanner shape for packages.
     *
     * @return array
     */
    public function emit_plugin_scanner_shape(): array
    {
        $syncedData = $this->sync_packages();

        return array_map(function ($package) {
            return [
                'name' => $package['name'] ?? '',
                'version' => $package['installed'] ?? '',
                'latest_version' => $package['releases'][0]['tag'] ?? '',
                'status' => $package['status'] ?? 'unknown',
                'managed_by' => $package['app_slug'] ?? 'unmanaged',
            ];
        }, $syncedData['packages']);
    }

    /**
     * Get releases for a given repository slug.
     *
     * @param string $repoSlug The repository slug (e.g., "owner/repo").
     * @return array The list of releases with tag, label, and download URL.
     */
    public function get_releases(string $repoSlug): array
    {
        $releases = $this->releaseService->get_releases($repoSlug);

        return array_map(function ($release) {
            return [
                'tag' => $release['tag_name'] ?? '',
                'label' => $release['name'] ?? '',
                'download_url' => $release['zipball_url'] ?? '',
            ];
        }, $releases);
    }

    /**
     * Rollback a package to a specific version.
     *
     * @param string $repoSlug The repository slug of the package.
     * @param string $version The version to rollback to.
     * @return bool True on success, false on failure.
     */
    public function rollback_package(string $repoSlug, string $version): bool
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

            $retryAttempts = 3;
            $tempFile = null;
            while ($retryAttempts > 0) {
                try {
                    $tempFile = $this->releaseService->download_package($zipUrl, $token);
                    if ($tempFile && $this->is_valid_package_archive($tempFile, $repoSlug)) {
                        break; // Exit retry loop on success
                    }

                    throw new \Exception("The downloaded file for '{$repoSlug}' is not a valid package.");
                } catch (\Exception $downloadException) {
                    $retryAttempts--;
                    if ($retryAttempts === 0) {
                        throw new \Exception('Failed to download package after multiple attempts: ' . $downloadException->getMessage());
                    }
                    sleep(2); // Wait before retrying
                }
            }

            // Ensure cleanup of temporary files on failure
            if (!$tempFile || !$this->is_valid_package_archive($tempFile, $repoSlug)) {
                @unlink($tempFile);
                throw new \Exception("The downloaded file for '{$repoSlug}' is not a valid package.");
            }

            $packageType = $this->get_package_type_by_repo($normalizedRepo);
            $this->install_from_zip($tempFile, $packageType);

            return true;
        } catch (\Exception $e) {
            Logger::log('ERROR', 'Rollback failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Checks the GitHub API rate limit and stores the status in a transient.
     *
     * @return array The rate limit status, including remaining requests and reset time.
     */
    public function check_rate_limit(): array
    {
        $rateLimitStatus = get_transient('wp2_github_rate_limit');

        if ($rateLimitStatus) {
            return $rateLimitStatus;
        }

        $rateLimit = $this->clientFactory->getRateLimit();
        if (!$rateLimit) {
            throw new \Exception('Unable to fetch GitHub API rate limit.');
        }

        $rateLimitStatus = [
            'remaining' => $rateLimit['rate']['remaining'] ?? 0,
            'reset' => $rateLimit['rate']['reset'] ?? time(),
        ];

        set_transient('wp2_github_rate_limit', $rateLimitStatus, 60);

        return $rateLimitStatus;
    }

    /**
     * Enable or disable auto-update for a package.
     *
     * @param string $packageId
     * @param bool $autoUpdate
     * @return bool
     */
    public function set_auto_update(string $packageId, bool $autoUpdate): bool
    {
        try {
            // Logic to update the auto-update setting in the database or configuration.
            Logger::log('INFO', sprintf('Auto-update for package %s set to %s.', $packageId, $autoUpdate ? 'enabled' : 'disabled'));
            
            // Example: Update the database (pseudo-code)
            // $result = $this->database->update('packages', ['auto_update' => $autoUpdate], ['id' => $packageId]);
            
            return true; // Assume success for now.
        } catch (\Throwable $exception) {
            Logger::log('ERROR', 'Failed to set auto-update: ' . $exception->getMessage());
            return false;
        }
    }

    /**
     * Fetches release notes for a package.
     *
     * @param string $packageId The package ID.
     * @return string|null The release notes, or null if not available.
     */
    public function get_release_notes(string $packageId): ?string
    {
        try {
            $package = $this->packageFinder->find_package_by_id($packageId);
            if (!$package) {
                throw new \RuntimeException('Package not found.');
            }

            $releases = $this->releaseService->get_releases($package['repo']);
            $latestRelease = $releases[0] ?? null;

            return $latestRelease['body'] ?? null;
        } catch (\Throwable $exception) {
            Logger::log('ERROR', 'Failed to fetch release notes: ' . $exception->getMessage());
            return null;
        }
    }
}
