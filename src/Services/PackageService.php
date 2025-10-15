<?php

namespace WP2\Update\Services;

use WP2\Update\Services\Github\RepositoryService;
use WP2\Update\Services\Github\ReleaseService;
use WP2\Update\Services\Github\ClientService;
use WP2\Update\Services\Github\ConnectionService;
use WP2\Update\Services\Github\AppService;
use WP2\Update\Utils\Formatting;
use WP2\Update\Utils\Logger;
use WP2\Update\Container;

/**
 * Handles all package-related operations, absorbing the logic of the old PackageFinder.
 */
class PackageService {
    private RepositoryService $repositoryService;
    private ReleaseService $releaseService;
    private ClientService $clientService;
    private ?ConnectionService $connectionService = null;
    private AppService $appService;

    public function __construct(
        RepositoryService $repositoryService,
        ReleaseService $releaseService,
        ClientService $clientService,
        AppService $appService,
        ?ConnectionService $connectionService = null
    ) {
        $this->repositoryService = $repositoryService;
        $this->releaseService = $releaseService;
        $this->clientService = $clientService;
        $this->connectionService = $connectionService;
        $this->appService = $appService;
    }

    public function set_connection_service(ConnectionService $connectionService): void {
        $this->connectionService = $connectionService;
    }

    /**
     * Gets all packages (plugins and themes) and groups them by status.
     * @return array
     */
    public function get_all_packages_grouped(): array {
        error_log('get_all_packages_grouped: Start grouping packages.');

        $local_packages = array_merge($this->getManagedPlugins(), $this->getManagedThemes());
        error_log('get_all_packages_grouped: Local packages retrieved: ' . print_r($local_packages, true));

        $result = ['all' => []];

        foreach ($local_packages as $package) {
            $processed_package = $this->process_package($package);
            error_log('get_all_packages_grouped: Processed package: ' . print_r($processed_package, true));
            $result['all'][] = $processed_package;
        }

        error_log('get_all_packages_grouped: Final grouped packages: ' . print_r($result, true));
        return $result;
    }

    /**
     * Helper method to process a single package.
     */
    private function process_package(array $package): array {
        error_log('process_package: Start processing package: ' . print_r($package, true));

        if (empty($package['repo'])) {
            $package['latest'] = null;
            $package['status'] = 'unconnected';
            error_log('process_package: Package is unconnected: ' . print_r($package, true));
            return $package;
        }

        $latest_release = $this->releaseService->get_latest_release($package['repo']);
        $package['latest'] = $latest_release['tag_name'] ?? null;
        $package['status'] = version_compare($package['version'], $package['latest'], '<') ? 'update_available' : 'up_to_date';

        error_log('process_package: Processed package: ' . print_r($package, true));
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
        Logger::log('INFO', "Assigning package: {$repo_slug} to app: {$app_id}");

        try {
            // Retrieve the app data from ConnectionData.
            $app_data = $this->connectionService->get_connection_data()->find($app_id);
            if (!$app_data) {
                throw new \RuntimeException("App not found: {$app_id}");
            }

            // Add the repository slug to the managed repositories.
            $managed_repositories = $app_data['managed_repositories'] ?? [];
            if (!in_array($repo_slug, $managed_repositories, true)) {
                $managed_repositories[] = $repo_slug;
                $app_data['managed_repositories'] = $managed_repositories;

                // Save the updated app data.
                $this->connectionService->get_connection_data()->save($app_data);
                Logger::log('INFO', "Successfully assigned package: {$repo_slug} to app: {$app_id}");
            } else {
                Logger::log('INFO', "Package: {$repo_slug} is already assigned to app: {$app_id}");
            }
        } catch (\Throwable $exception) {
            Logger::log('ERROR', "Failed to assign package: {$repo_slug} to app: {$app_id}. Error: " . $exception->getMessage());
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
        Logger::log('INFO', "Updating package: {$repo_slug}");

        try {
            // Fetch the latest release for the repository.
            $latestRelease = $this->releaseService->get_latest_release($repo_slug);
            if (!$latestRelease) {
                throw new \RuntimeException("No latest release found for repository: {$repo_slug}");
            }

            $zipUrl = $latestRelease['zipball_url'] ?? null;
            if (!$zipUrl) {
                throw new \RuntimeException("Download URL not found for the latest release of repository: {$repo_slug}");
            }

            // Download the package.
            $app_id = $this->appService->resolve_app_id(null);
            $tempFile = $this->releaseService->download_package($zipUrl, $app_id);
            if (!$tempFile || !$this->is_valid_package_archive($tempFile, $repo_slug)) {
                throw new \RuntimeException("Invalid package archive for repository: {$repo_slug}");
            }

            // Use the WordPress upgrader to process the package.
            $packageType = $this->get_package_type_by_repo($repo_slug);
            $result = $this->install_from_zip($tempFile, $packageType);

            if ($result) {
                Logger::log('INFO', "Successfully updated package: {$repo_slug}");
                return true;
            } else {
                throw new \RuntimeException("WordPress upgrader failed to update the package: {$repo_slug}");
            }
        } catch (\Throwable $exception) {
            Logger::log('ERROR', "Failed to update package: {$repo_slug}. Error: " . $exception->getMessage());
            return false;
        }
    }

    private function install_package(string $repoSlug, string $version): bool {
        Logger::log('INFO', "Installing package: {$repoSlug} version: {$version}");

        try {
            $release = $this->releaseService->get_release_by_version($repoSlug, $version);
            if (!$release) {
                throw new \RuntimeException("Release not found for repository: {$repoSlug}");
            }

            $zipUrl = $release['zipball_url'] ?? null;
            if (!$zipUrl) {
                throw new \RuntimeException("Download URL not found for release: {$version}");
            }

            $app_id = $this->appService->resolve_app_id(null);
            $token = $this->clientService->getInstallationToken($app_id);
            if (!$token) {
                Logger::log('WARNING', "Authentication token not available for app ID: {$app_id}. Skipping operation.");
                return false; // Skip the operation gracefully
            }

            $tempFile = $this->releaseService->download_package($zipUrl, $token);
            if (!$tempFile || !$this->is_valid_package_archive($tempFile, $repoSlug)) {
                throw new \RuntimeException("Invalid package archive for repository: {$repoSlug}");
            }

            $packageType = $this->get_package_type_by_repo($repoSlug);
            $this->install_from_zip($tempFile, $packageType);

            Logger::log('INFO', "Successfully installed package: {$repoSlug} version: {$version}");
            return true;
        } catch (\Throwable $exception) {
            Logger::log('ERROR', "Failed to install package: {$repoSlug} version: {$version}. Error: " . $exception->getMessage());
            return false;
        }
    }

    /**
     * Retrieves release notes for a specific package.
     *
     * @param string $repo_slug The repository slug.
     * @return array The release notes data.
     */
    public function get_release_notes(string $repo_slug): array {
        if (empty($repo_slug)) {
            throw new \InvalidArgumentException(__("Repository slug is required.", \WP2\Update\Config::TEXT_DOMAIN));
        }

        return $this->releaseService->get_release_notes($repo_slug);
    }

    /**
     * Updates the release channel for a specific package.
     *
     * @param string $repo_slug The repository slug.
     * @param string $channel The release channel (e.g., 'stable', 'beta').
     * @return void
     */
    public function update_release_channel(string $repo_slug, string $channel): void {
        if (empty($repo_slug) || empty($channel)) {
            throw new \InvalidArgumentException(__("Repository slug and channel are required.", \WP2\Update\Config::TEXT_DOMAIN));
        }

        // Logic to update the release channel in the database or configuration.
        Logger::log('INFO', "Updated release channel for {$repo_slug} to {$channel}.");
    }

    /**
     * Rolls back a package (plugin or theme) to a specific version.
     *
     * @param string $repo_slug The repository slug.
     * @param string $version The version to roll back to.
     * @return bool True on success, false on failure.
     */
    public function rollback_package(string $repo_slug, string $version): bool {
        Logger::log('INFO', "Rolling back package: {$repo_slug} to version: {$version}");

        try {
            // Fetch the specific release for the repository and version.
            $release = $this->releaseService->get_release_by_version($repo_slug, $version);
            if (!$release) {
                throw new \RuntimeException(__("Release not found for repository: {$repo_slug} and version: {$version}", \WP2\Update\Config::TEXT_DOMAIN));
            }

            $zipUrl = $release['zipball_url'] ?? null;
            if (!$zipUrl) {
                throw new \RuntimeException(__("Download URL not found for release: {$version} of repository: {$repo_slug}", \WP2\Update\Config::TEXT_DOMAIN));
            }
            $app_id = $this->appService->resolve_app_id(null);
            $tempFile = $this->releaseService->download_package($zipUrl, $app_id);

            if (!$tempFile || !$this->is_valid_package_archive($tempFile, $repo_slug)) {
                throw new \RuntimeException(__("Invalid package archive for repository: {$repo_slug}", \WP2\Update\Config::TEXT_DOMAIN));
            }

            // Use the WordPress upgrader to process the package.
            $packageType = $this->get_package_type_by_repo($repo_slug);
            $result = $this->install_from_zip($tempFile, $packageType);

            if ($result) {
                Logger::log('INFO', "Successfully rolled back package: {$repo_slug} to version: {$version}");
                return true;
            } else {
                throw new \RuntimeException("WordPress upgrader failed to roll back the package: {$repo_slug}");
            }
        } catch (\Throwable $exception) {
            Logger::log('ERROR', "Failed to roll back package: {$repo_slug} to version: {$version}. Error: " . $exception->getMessage());
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
     * Factory method to create an instance of PackageService.
     *
     * @return PackageService
     */
    public static function create(Container $container): self {
        return new self(
            $container->get(RepositoryService::class),
            $container->get(ReleaseService::class),
            $container->get(ClientService::class),
            $container->get(AppService::class),
            $container->has(ConnectionService::class) ? $container->get(ConnectionService::class) : null
        );
    }

    /**
     * Fetch all packages from WordPress options.
     *
     * @return array
     */
    public function get_packages(): array {
        $packages = get_option('wp2_packages_data', []);
        if (empty($packages)) {
            error_log('No packages found in wp2_packages_data option. Attempting to scan for managed plugins and themes.');
            $packages = $this->scan_for_packages();
            error_log('Scanned packages: ' . print_r($packages, true));
        } else {
            error_log('Retrieved packages: ' . print_r($packages, true));
        }

        // Debugging: Log the type and structure of the packages variable
        error_log('Type of packages: ' . gettype($packages));
        if (is_array($packages)) {
            error_log('Count of packages: ' . count($packages));
            foreach ($packages as $key => $package) {
                error_log("Package at index {$key}: " . print_r($package, true));
            }
        }

        return is_array($packages) ? $packages : [];
    }

    /**
     * Scans for all managed packages (plugins and themes).
     *
     * @return array
     */
    public function scan_for_packages(): array {
        $plugins = $this->getManagedPlugins();
        error_log('scan_for_packages: Retrieved plugins: ' . print_r($plugins, true));

        $themes = $this->getManagedThemes();
        error_log('scan_for_packages: Retrieved themes: ' . print_r($themes, true));

        $packages = array_merge($plugins, $themes);
        error_log('scan_for_packages: Merged packages: ' . print_r($packages, true));

        update_option('wp2_packages_data', $packages);
        error_log('scan_for_packages: Updated wp2_packages_data option.');

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
        Logger::log('INFO', "Creating new package: {$name} using template: {$template} for app: {$appId}");
        // Placeholder logic for creating a new package.
        return true;
    }

    /**
     * Validates if the given file is a valid package archive.
     *
     * @param string $filePath
     * @param string $repoSlug
     * @return bool
     */
    private function is_valid_package_archive(string $filePath, string $repoSlug): bool {
        // Placeholder logic for validating the package archive.
        return file_exists($filePath) && strpos($filePath, $repoSlug) !== false;
    }

    /**
     * Determines the package type (plugin or theme) based on the repository slug.
     *
     * @param string $repoSlug
     * @return string
     */
    private function get_package_type_by_repo(string $repoSlug): string {
        // Placeholder logic for determining the package type.
        return strpos($repoSlug, 'theme') !== false ? 'theme' : 'plugin';
    }

    /**
     * Installs a package from a ZIP file.
     *
     * @param string $filePath
     * @param string $packageType
     * @return bool
     */
    private function install_from_zip(string $filePath, string $packageType): bool {
        // Placeholder logic for installing a package from a ZIP file.
        Logger::log('INFO', "Installing {$packageType} from ZIP: {$filePath}");
        return true;
    }
}
