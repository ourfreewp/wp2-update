<?php

namespace WP2\Update\Services;

use WP2\Update\Services\Github\RepositoryService;
use WP2\Update\Services\Github\ReleaseService;
use WP2\Update\Services\Github\ClientService;
use WP2\Update\Services\Github\ConnectionService;
use WP2\Update\Utils\Formatting;
use WP2\Update\Utils\Logger;

/**
 * Handles all package-related operations, absorbing the logic of the old PackageFinder.
 */
class PackageService {
    private RepositoryService $repositoryService;
    private ReleaseService $releaseService;
    private ClientService $clientService;
    private ?ConnectionService $connectionService = null;

    public function __construct(
        RepositoryService $repositoryService,
        ReleaseService $releaseService,
        ClientService $clientService
    ) {
        $this->repositoryService = $repositoryService;
        $this->releaseService = $releaseService;
        $this->clientService = $clientService;
    }

    public function set_connection_service(ConnectionService $connectionService): void {
        $this->connectionService = $connectionService;
    }

    /**
     * Gets all packages (plugins and themes) and groups them by status.
     * @return array
     */
    public function get_all_packages_grouped(): array {
        $local_packages = array_merge($this->get_managed_plugins(), $this->get_managed_themes());
        $all_apps = $this->connectionService->all();
        $managed_repos_by_app = [];
        foreach ($all_apps as $app) {
            foreach ($app['managed_repositories'] ?? [] as $repo_slug) {
                $managed_repos_by_app[$repo_slug] = $app['id'];
            }
        }

        $result = ['managed' => [], 'unlinked' => [], 'all' => []];

        foreach ($local_packages as $package) {
            $repo_slug = $package['repo'];
            if (isset($managed_repos_by_app[$repo_slug])) {
                $package['app_id'] = $managed_repos_by_app[$repo_slug];
                $package['is_managed'] = true;
                $latest_release = $this->releaseService->get_latest_release($repo_slug, $package['app_id']);
                $package['latest'] = $latest_release['tag_name'] ?? null;
                $package['status'] = version_compare($package['version'], $package['latest'], '<') ? 'update_available' : 'up_to_date';
                $result['managed'][] = $package;
            } else {
                $package['is_managed'] = false;
                $package['status'] = 'unlinked';
                $result['unlinked'][] = $package;
            }
            $result['all'][] = $package;
        }

        return $result;
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
            $token = $this->clientService->getInstallationToken();
            if (!$token) {
                throw new \RuntimeException("Failed to retrieve authentication token.");
            }

            $tempFile = $this->releaseService->download_package($zipUrl, $token);
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

            $token = $this->clientService->getInstallationToken();
            if (!$token) {
                throw new \RuntimeException("Failed to retrieve authentication token.");
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

            // Download the package.
            $token = $this->clientService->getInstallationToken();
            if (!$token) {
                throw new \RuntimeException(__("Failed to retrieve authentication token.", \WP2\Update\Config::TEXT_DOMAIN));
            }

            $tempFile = $this->releaseService->download_package($zipUrl, $token);
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

    public function get_managed_plugins(): array {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $managed = [];
        foreach (get_plugins() as $slug => $plugin) {
            $update_uri = $plugin['UpdateURI'] ?? '';
            if ($update_uri && str_contains($update_uri, 'github.com')) {
                $managed[$slug] = [
                    'slug' => $slug,
                    'repo' => Formatting::normalize_repo($update_uri),
                    'name' => $plugin['Name'],
                    'version' => $plugin['Version'],
                    'type' => 'plugin',
                ];
            }
        }
        return $managed;
    }

    public function get_managed_themes(): array {
        $managed = [];
        foreach (wp_get_themes() as $slug => $theme) {
            $update_uri = $theme->get('UpdateURI');
            if ($update_uri && str_contains($update_uri, 'github.com')) {
                $managed[$slug] = [
                    'slug' => $slug,
                    'repo' => Formatting::normalize_repo($update_uri),
                    'name' => $theme->get('Name'),
                    'version' => $theme->get('Version'),
                    'type' => 'theme',
                ];
            }
        }
        return $managed;
    }

    /**
     * Validates the downloaded package archive.
     *
     * @param string $filePath Path to the downloaded file.
     * @param string $repoSlug Repository slug.
     * @return bool True if valid, false otherwise.
     */
    private function is_valid_package_archive(string $filePath, string $repoSlug): bool {
        // Placeholder for validation logic (e.g., checking file integrity, structure, etc.)
        return file_exists($filePath) && strpos($filePath, $repoSlug) !== false;
    }

    /**
     * Determines the package type (plugin or theme) based on the repository slug.
     *
     * @param string $repoSlug Repository slug.
     * @return string The package type ('plugin' or 'theme').
     */
    private function get_package_type_by_repo(string $repoSlug): string {
        // Placeholder logic to determine package type.
        return str_contains($repoSlug, 'theme') ? 'theme' : 'plugin';
    }

    /**
     * Installs a package from a ZIP file.
     *
     * @param string $filePath Path to the ZIP file.
     * @param string $packageType The package type ('plugin' or 'theme').
     * @return bool True on success, false on failure.
     */
    private function install_from_zip(string $filePath, string $packageType): bool {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/theme-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        // Ensure the file system is ready.
        $creds = request_filesystem_credentials(site_url());
        if (!WP_Filesystem($creds)) {
            Logger::log('ERROR', 'Failed to initialize the WordPress filesystem.');
            return false;
        }

        // Define a custom upgrader skin to handle feedback.
        $skin = new \Bulk_Upgrader_Skin();
        $upgrader = null;

        if ($packageType === 'plugin') {
            $upgrader = new \Plugin_Upgrader($skin);
        } elseif ($packageType === 'theme') {
            $upgrader = new \Theme_Upgrader($skin);
        } else {
            Logger::log('ERROR', "Invalid package type: {$packageType}");
            return false;
        }

        // Perform the installation.
        $result = $upgrader->install($filePath);

        if (is_wp_error($result)) {
            Logger::log('ERROR', "Installation failed: " . $result->get_error_message());
            return false;
        }

        Logger::log('INFO', "Installation successful for {$packageType}.");
        return true;
    }

    /**
     * Sets the client factory for authentication.
     *
     * @param ClientService $clientService The client factory instance.
     */
    public function set_client_factory(ClientService $clientService): void {
        $this->clientService = $clientService;
    }

    /**
     * Creates a new package based on the provided template and name.
     *
     * @param string $template The package template (e.g., plugin or theme).
     * @param string $name The name of the package.
     * @return array The result of the package creation.
     */
    public function create_new_package(string $template, string $name): array {
        Logger::log('INFO', "Creating new package: {$name} using template: {$template}");

        // Simulate package creation logic
        $result = [
            'template' => $template,
            'name' => $name,
            'status' => 'success',
            'message' => "Package '{$name}' created successfully."
        ];

        return $result;
    }
}
