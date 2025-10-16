<?php
declare(strict_types=1);

namespace WP2\Update\Services;

use WP2\Update\Services\Github\RepositoryService;
use WP2\Update\Services\Github\ReleaseService;
use WP2\Update\Services\Github\ClientService;
use WP2\Update\Services\Github\AppService;
use WP2\Update\Utils\Logger;
use WP2\Update\Utils\CustomException;
use WP2\Update\Config;
use WP2\Update\Data\DTO\AppDTO;
use WP2\Update\Utils\Cache;


/**
 * Class PackageService
 *
 * Handles all package-related operations, absorbing the logic of the old PackageFinder.
 */
class PackageService {
    /**
     * @var RepositoryService Handles repository-related operations.
     */
    private RepositoryService $repositoryService;

    /**
     * @var ReleaseService Handles release-related operations.
     */
    private ReleaseService $releaseService;

    /**
     * @var ClientService Handles GitHub client interactions.
     */
    private ClientService $clientService;

    /**
     * @var AppService Handles app-related operations.
     */
    private AppService $appService;

    /**
     * Constructor for PackageService.
     *
     * @param RepositoryService $repositoryService Handles repository-related operations.
     * @param ReleaseService $releaseService Handles release-related operations.
     * @param ClientService $clientService Handles GitHub client interactions.
     * @param AppService $appService Handles app-related operations.
     */
    public function __construct(
        RepositoryService $repositoryService,
        ReleaseService $releaseService,
        ClientService $clientService,
        AppService $appService
    ) {
        $this->repositoryService = $repositoryService;
        $this->releaseService = $releaseService;
        $this->clientService = $clientService;
        $this->appService = $appService;
    }

    /**
     * Gets all packages (plugins and themes) and groups them by status.
     * @return array
     */
    public function get_all_packages_grouped(): array {
        // Implement caching for package data
        $cacheKey = 'all_packages_grouped';
        $cachedData = Cache::get($cacheKey);

        if ($cachedData !== false) {
            return $cachedData;
        }

        $local_packages = array_merge($this->getManagedPlugins() ?? [], $this->getManagedThemes() ?? []);

        // Validate that $local_packages is iterable
        if (!is_array($local_packages)) {
            return ['all' => []];
        }

        $result = ['all' => []];

        foreach ($local_packages as $package) {
            $processed_package = $this->process_package($package);
            $result['all'][] = $processed_package;
        }

        // Store the result in cache
        Cache::set($cacheKey, $result, 3600); // Cache for 1 hour

        return $result;
    }

    /**
     * Helper method to process a single package.
     */
    private function process_package(array $package): array {
        Logger::assert(!empty($package['repo']), 'Package is missing a repository slug.', $package);

        if (empty($package['repo'])) {
            $package['latest'] = null;
            $package['status'] = 'unconnected';
            return $package;
        }

        $latest_release = $this->releaseService->get_latest_release($package['repo']);
        $package['latest'] = $latest_release['tag_name'] ?? null;
        $package['status'] = version_compare($package['version'], $package['latest'], '<') ? 'update_available' : 'up_to_date';

        return $package;
    }

    /**
     * Assigns a package to an app by updating the app's managed repositories.
     *
     * @param string $app_id The app ID.
     * @param string $repo_slug The repository slug.
     * @return void
     */
    public function assign_package_to_app(string $app_id, string $repo_slug): void {
        // Validate inputs
        if (empty($app_id) || !is_string($app_id)) {
            throw new \InvalidArgumentException('Invalid app ID provided.');
        }

        if (empty($repo_slug) || !is_string($repo_slug)) {
            throw new \InvalidArgumentException('Invalid repository slug provided.');
        }

        try {
            // Retrieve the app data from AppService.
            $app_data = $this->appService->get_connection_data()->find($app_id);
            if (!$app_data) {
                throw new \RuntimeException("App not found: {$app_id}");
            }

            $app_data_array = $app_data->toArray();
            $managed_repositories = $app_data_array['managed_repositories'] ?? [];
            if (!in_array($repo_slug, $managed_repositories, true)) {
                $managed_repositories[] = $repo_slug;
                $app_data_array['managed_repositories'] = $managed_repositories;

                // Save the updated app data.
                $this->appService->get_connection_data()->save(AppDTO::fromArray($app_data_array));
            }
        } catch (\Throwable $exception) {
            throw $exception;
        }
    }

    /**
     * Updates a package (plugin or theme) to the latest version.
     *
     * @param string $repo_slug The repository slug.
     * @return bool True on success, false on failure.
     */
    public function update_package(string $repo_slug): bool {
        // Validate input
        if (empty($repo_slug) || !is_string($repo_slug)) {
            throw new \InvalidArgumentException('Invalid repository slug provided.');
        }

        Logger::info('Package update process started.', ['repo_slug' => $repo_slug]);
        Logger::start('package_update_' . str_replace('/', '_', $repo_slug));

        try {
            Logger::assert(!empty($repo_slug), 'Repository slug is empty.', ['repo_slug' => $repo_slug]);

            $latestRelease = $this->releaseService->get_latest_release($repo_slug);
            if (!$latestRelease) {
                throw new \RuntimeException("No latest release found for repository: {$repo_slug}");
            }

            $zipUrl = $latestRelease['zipball_url'] ?? null;
            if (!$zipUrl) {
                throw new \RuntimeException("Download URL not found for the latest release of repository: {$repo_slug}");
            }

            Logger::debug('Latest release found.', ['repo_slug' => $repo_slug, 'version' => $latestRelease['tag_name'], 'zip_url' => $zipUrl]);

            $app_id = $this->appService->resolve_app_id(null);
            $tempFile = $this->releaseService->download_package($zipUrl, $app_id);

            Logger::assert($tempFile && $this->is_valid_package_archive($tempFile, $repo_slug), 'Package archive is invalid or download failed.', ['repo_slug' => $repo_slug, 'temp_file' => $tempFile]);

            $packageType = $this->get_package_type_by_repo($repo_slug);
            $result = $this->install_from_zip($tempFile, $packageType);

            if (!$result) {
                throw new \RuntimeException("WordPress upgrader failed to update the package: {$repo_slug}");
            }

            Logger::info('Package update successful.', ['repo_slug' => $repo_slug]);
            Logger::stop('package_update_' . str_replace('/', '_', $repo_slug));
            return true;
        } catch (\Throwable $exception) {
            Logger::error('Package update failed.', ['repo_slug' => $repo_slug, 'exception' => $exception->getMessage()]);
            Logger::stop('package_update_' . str_replace('/', '_', $repo_slug));
            return false;
        }
    }

    public function rollback_package(string $repo_slug, string $version): bool {
        Logger::info('Package rollback process started.', ['repo_slug' => $repo_slug, 'version' => $version]);
        Logger::start('package_rollback_' . str_replace('/', '_', $repo_slug));

        try {
            Logger::assert(!empty($repo_slug) && !empty($version), 'Repository slug or version is empty.', ['repo_slug' => $repo_slug, 'version' => $version]);

            $release = $this->releaseService->get_release_by_version($repo_slug, $version);
            if (!$release) {
                throw new \RuntimeException("Release not found for repository: {$repo_slug} and version: {$version}");
            }

            $zipUrl = $release['zipball_url'] ?? null;
            if (!$zipUrl) {
                throw new \RuntimeException("Download URL not found for release: {$version} of repository: {$repo_slug}");
            }

            Logger::debug('Release found for rollback.', ['repo_slug' => $repo_slug, 'version' => $version, 'zip_url' => $zipUrl]);

            $app_id = $this->appService->resolve_app_id(null);
            $tempFile = $this->releaseService->download_package($zipUrl, $app_id);

            Logger::assert($tempFile && $this->is_valid_package_archive($tempFile, $repo_slug), 'Package archive is invalid or download failed.', ['repo_slug' => $repo_slug, 'temp_file' => $tempFile]);

            $packageType = $this->get_package_type_by_repo($repo_slug);
            $result = $this->install_from_zip($tempFile, $packageType);

            if (!$result) {
                throw new \RuntimeException("WordPress upgrader failed to roll back the package: {$repo_slug}");
            }

            Logger::info('Package rollback successful.', ['repo_slug' => $repo_slug, 'version' => $version]);
            Logger::stop('package_rollback_' . str_replace('/', '_', $repo_slug));
            return true;
        } catch (\Throwable $exception) {
            Logger::error('Package rollback failed.', ['repo_slug' => $repo_slug, 'version' => $version, 'exception' => $exception->getMessage()]);
            Logger::stop('package_rollback_' . str_replace('/', '_', $repo_slug));
            return false;
        }
    }

    // --- Former PackageFinder Methods ---

    /**
     * Retrieves all managed plugins.
     *
     * @return array
     */
    public function getManagedPlugins(): array {
        // Fetch plugins with the UpdateURI header set to GitHub.
        $plugins = get_plugins();
        $managedPlugins = [];

        foreach ($plugins as $pluginFile => $pluginData) {
            if (!empty($pluginData['UpdateURI']) && strpos($pluginData['UpdateURI'], 'github.com') !== false) {
                $managedPlugins[] = [
                    'name' => $pluginData['Name'],
                    'version' => $pluginData['Version'],
                    'repo' => $pluginData['UpdateURI'],
                ];
            }
        }

        return $managedPlugins;
    }

    /**
     * Retrieves all managed themes.
     *
     * @return array
     */
    public function getManagedThemes(): array {
        // Fetch themes with the UpdateURI header set to GitHub.
        $themes = wp_get_themes();
        $managedThemes = [];

        foreach ($themes as $theme) {
            if (!empty($theme->get('UpdateURI')) && strpos($theme->get('UpdateURI'), 'github.com') !== false) {
                $managedThemes[] = [
                    'name' => $theme->get('Name'),
                    'version' => $theme->get('Version'),
                    'repo' => $theme->get('UpdateURI'),
                ];
            }
        }

        return $managedThemes;
    }

    /**
     * Processes a single package to determine its update status.
     *
     * @param array $package
     * @return array
     */
    public function processPackage(array $package): array {
        $latestRelease = $this->releaseService->get_latest_release($package['repo']);
        $package['latest'] = $latestRelease['tag_name'] ?? null;
        $package['status'] = version_compare($package['version'], $package['latest'], '<') ? 'update_available' : 'up_to_date';

        return $package;
    }

    /**
     * Determines whether to use site-level options for multisite compatibility.
     */
    private function get_option_function(): callable
    {
        return is_multisite() ? 'get_site_option' : 'get_option';
    }

    private function update_option_function(): callable
    {
        return is_multisite() ? 'update_site_option' : 'update_option';
    }

    /**
     * Fetch all packages from WordPress options.
     *
     * @return array
     */
    public function get_packages(): array {
        $get_option = $this->get_option_function();
        $packages = $get_option(Config::OPTION_PACKAGES_DATA, []);
        if (empty($packages)) {
            $packages = $this->scan_for_packages();
        }

        return is_array($packages) ? $packages : [];
    }

    /**
     * Scans for all managed packages (plugins and themes).
     *
     * @return array
     */
    public function scan_for_packages(): array {
        $update_option = $this->update_option_function();
        $plugins = $this->getManagedPlugins();

        $themes = $this->getManagedThemes();

        $packages = array_merge($plugins, $themes);

        $update_option(Config::OPTION_PACKAGES_DATA, $packages);

        return $packages;
    }

    /**
     * Retrieves paginated packages.
     *
     * @param int $page The page number.
     * @param int $perPage The number of items per page.
     * @return array The paginated packages.
     */
    public function get_paginated_packages(int $page, int $perPage): array {
        $allPackages = $this->get_all_packages_grouped()['all'];
        $offset = ($page - 1) * $perPage;
        return array_slice($allPackages, $offset, $perPage);
    }

    /**
     * Retrieves all packages.
     *
     * @return array The list of all packages.
     */
    public function get_all_packages(): array {
        return $this->get_all_packages_grouped()['all'];
    }

    /**
     * Creates a new package.
     *
     * @param string $template
     * @param string $name
     * @param string $appId
     * @return bool
     */
    public function create_new_package(string $template, string $name, string $appId): bool {
        // Placeholder logic for creating a new package.
        return true;
    }

    /**
     * Validates if the given ZIP file is a valid WordPress plugin or theme archive.
     *
     * @param string $filePath Path to the ZIP file.
     * @param string $repoSlug Repository slug for logging purposes.
     * @return bool True if valid, false otherwise.
     */
    private function is_valid_package_archive(string $filePath, string $repoSlug): bool {
        Logger::info('Validating package archive.', ['filePath' => $filePath, 'repoSlug' => $repoSlug]);

        $tempDir = sys_get_temp_dir() . '/wp2_update_' . uniqid();
        if (!mkdir($tempDir) && !is_dir($tempDir)) {
            Logger::error('Failed to create temporary directory for validation.', ['tempDir' => $tempDir]);
            return false;
        }

        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            Logger::error('Failed to open ZIP archive.', ['filePath' => $filePath]);
            return false;
        }

        $zip->extractTo($tempDir);
        $zip->close();

        $isValid = false;
        if (file_exists($tempDir . '/plugin.php')) {
            $isValid = true;
            Logger::info('Valid plugin archive detected.', ['repoSlug' => $repoSlug]);
        } elseif (file_exists($tempDir . '/style.css')) {
            $isValid = true;
            Logger::info('Valid theme archive detected.', ['repoSlug' => $repoSlug]);
        } else {
            Logger::warning('Invalid package archive: Missing plugin.php or style.css.', ['repoSlug' => $repoSlug]);
        }

        // Clean up temporary directory
        $this->delete_directory($tempDir);

        return $isValid;
    }

    /**
     * Recursively deletes a directory.
     *
     * @param string $dir Directory path.
     * @return void
     */
    private function delete_directory(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }

        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->delete_directory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    /**
     * Determines the package type (plugin or theme) for a given repository.
     *
     * @param string $repo_slug The repository slug (e.g., 'owner/repo').
     * @return string|null The package type ('plugin', 'theme') or null if undetermined.
     */
    public function get_package_type_by_repo(string $repo_slug): ?string {
        $tempFile = $this->releaseService->download_package_by_repo($repo_slug);
        if (!$tempFile) {
            return null;
        }

        $zip = new \ZipArchive();
        if ($zip->open($tempFile) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $fileName = $zip->getNameIndex($i);

                if (stripos($fileName, 'style.css') !== false) {
                    $zip->close();
                    unlink($tempFile);
                    return 'theme';
                }

                if (stripos($fileName, 'plugin.php') !== false) {
                    $zip->close();
                    unlink($tempFile);
                    return 'plugin';
                }
            }
            $zip->close();
        }

        unlink($tempFile);
        return null;
    }

    /**
     * Installs a package from a ZIP file.
     *
     * @param string $zipFilePath Path to the ZIP file.
     * @param string $packageType The type of the package ('plugin' or 'theme').
     * @return bool True on success, false on failure.
     */
    public function install_from_zip(string $zipFilePath, string $packageType): bool {
        if (!file_exists($zipFilePath)) {
            return false;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        $upgrader = null;
        if ($packageType === 'plugin') {
            require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
            $upgrader = new \Plugin_Upgrader();
        } elseif ($packageType === 'theme') {
            require_once ABSPATH . 'wp-admin/includes/class-theme-upgrader.php';
            $upgrader = new \Theme_Upgrader();
        }

        if (!$upgrader) {
            return false;
        }

        $result = $upgrader->install($zipFilePath);
        return $result !== false;
    }

    /**
     * Checks if a repository is available and accessible for the given app.
     *
     * @param string $repo_name The name of the repository.
     * @param string $app_id The ID of the app.
     * @return bool True if the repository is available, false otherwise.
     * @throws \Exception If the GitHub API call fails.
     */
    public function check_repository_availability(string $repo_name, string $app_id): bool {
        try {
            $client = $this->clientService->getInstallationClient($app_id);
            $repo = $client->repo()->show($app_id, $repo_name);
            return !empty($repo);
        } catch (\Exception $e) {
            throw new CustomException(
                __('Failed to check repository availability: ', Config::TEXT_DOMAIN) . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Retrieves release notes for a specific version of a package.
     *
     * @param string $repo_slug The repository slug.
     * @param string $version The version to retrieve release notes for.
     * @return array The release notes data.
     */
    public function get_version_release_notes(string $repo_slug, string $version): array {
        $release = $this->releaseService->get_release_by_version($repo_slug, $version);
        return $release['body'] ?? [];
    }

    /**
     * Updates the release channel for a specific package.
     *
     * @param string $repo_slug The repository slug.
     * @param string $channel The new release channel.
     * @return void
     */
    public function update_release_channel(string $repo_slug, string $channel): void {
        // Logic to update the release channel for the package.
        $this->repositoryService->update_channel($repo_slug, $channel);
    }
}
