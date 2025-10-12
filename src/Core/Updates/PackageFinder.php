<?php

namespace WP2\Update\Core\Updates;

use WP2\Update\Utils\Formatting;
use WP2\Update\Utils\Logger;
use WP2\Update\Core\API\RepositoryService;
use WP2\Update\Core\API\ConnectionService;

/**
 * Scans the installation for themes and plugins that declare an Update URI.
 */
class PackageFinder
{
    private RepositoryService $repositoryService;

    private ?ConnectionService $connectionService;

    private $fetchReleasesCallback;

    public function __construct(RepositoryService $repositoryService, ?ConnectionService $connectionService, callable $fetchReleasesCallback)
    {
        $this->repositoryService = $repositoryService;
        $this->connectionService = $connectionService;
        $this->fetchReleasesCallback = $fetchReleasesCallback;
    }

    public function setConnectionService(ConnectionService $connectionService): void
    {
        $this->connectionService = $connectionService;
    }

    /**
     * Ensures the `get_plugins` function is available.
     */
    private function ensure_get_plugins_available(): void
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }

    /**
     * Return plugins managed by the GitHub updater.
     *
     * @return array<string,array{
     *     slug:string,
     *     repo:string,
     *     name:string,
     *     version:string,
     *     type:string,
     *     app_slug:string
     * }>
     */
    public function get_managed_plugins(): array
    {
        Logger::log('DEBUG', 'get_managed_plugins method called.');

        $this->ensure_get_plugins_available();

        $managed = [];

        foreach (get_plugins() as $slug => $plugin) {
            $updateUri = $plugin['UpdateURI'] ?? $plugin['Update URI'] ?? '';

            if (!$updateUri) {
                continue; // Skip plugins without an UpdateURI.
            }

            Logger::log('DEBUG', "Plugin detected: Slug={$slug}, UpdateURI={$updateUri}");

            $managed[$slug] = [
                'slug'     => $slug,
                'repo'     => Formatting::normalize_repo($updateUri),
                'name'     => $plugin['Name'] ?? $slug,
                'version'  => $plugin['Version'] ?? '0.0.0',
                'type'     => 'plugin',
                'app_slug' => sanitize_title($plugin['Name'] ?? $slug),
            ];
        }

        return $managed;
    }

    /**
     * Return themes managed by the GitHub updater.
     *
     * @return array<string,array{
     *     slug:string,
     *     repo:string,
     *     name:string,
     *     version:string,
     *     type:string,
     *     app_slug:string
     * }>
     */
    public function get_managed_themes(): array
    {
        Logger::log('DEBUG', 'get_managed_themes method called.');

        $managed = [];

        foreach (wp_get_themes() as $slug => $theme) {
            $updateUri = $theme->get('UpdateURI') ?: $theme->get('Update URI');

            if (!$updateUri) {
                continue; // Skip themes without an UpdateURI.
            }

            Logger::log('DEBUG', "Theme detected: Slug={$slug}, UpdateURI={$updateUri}");

            $managed[$slug] = [
                'slug'     => $slug,
                'repo'     => Formatting::normalize_repo($updateUri),
                'name'     => $theme->get('Name') ?: $slug,
                'version'  => $theme->get('Version') ?: '0.0.0',
                'type'     => 'theme',
                'app_slug' => sanitize_title($theme->get('Name') ?: $slug),
            ];
        }

        return $managed;
    }

    /**
     * Combined list of all managed packages.
     *
     * @return array<int,array<string,string>>
     */
    public function get_managed_packages(): array
    {
        Logger::log('DEBUG', 'get_managed_packages called. Managed plugins: ' . print_r($this->get_managed_plugins(), true));
        Logger::log('DEBUG', 'get_managed_packages called. Managed themes: ' . print_r($this->get_managed_themes(), true));

        return array_values(
            array_merge(
                $this->get_managed_plugins(),
                $this->get_managed_themes()
            )
        );
    }

    /**
     * Write managed repositories back to the app record.
     *
     * @param string $appId The ID of the app.
     * @param array $repositories The repositories to associate with the app.
     * @return void
     */
    public function write_managed_repositories(int $appId): void
    {
        Logger::log('INFO', "Writing managed repositories for app {$appId}.");

        $app = $this->repositoryService->find_app($appId);
        if (!$app) {
            Logger::log('ERROR', "App with ID {$appId} not found.");
            return;
        }

        $app['managed_repositories'] = $this->get_managed_packages();
        $this->repositoryService->save_app($app);

        Logger::log('INFO', "Managed repositories written for app {$appId}.");
    }

    /**
     * Update the app record when packages are assigned or removed.
     *
     * @param string $appId The ID of the app.
     * @param array $packages The list of packages to assign.
     * @return void
     */
    public function update_managed_repositories(string $appId, array $packages): void
    {
        $app = $this->repositoryService->find_app($appId);
        if (!$app) {
            Logger::log('WARNING', "Attempted to update repositories for unknown app {$appId}.");
            return;
        }

        $managed = array_values(array_filter(array_map(
            static function ($package) {
                if (is_array($package)) {
                    $repo = $package['repo'] ?? $package['repository'] ?? null;
                } else {
                    $repo = $package;
                }

                return Formatting::normalize_repo($repo);
            },
            $packages
        )));

        $app['managed_repositories'] = $managed;
        $this->repositoryService->save_app($app);

        Logger::log('INFO', "Updated managed repositories for app {$appId}: " . json_encode($managed));
    }

    /**
     * Finds a package by its ID.
     *
     * @param string $packageId The package ID.
     * @return array|null The package data, or null if not found.
     */
    public function find_package_by_id(string $packageId): ?array
    {
        $packages = $this->get_managed_packages();

        foreach ($packages as $package) {
            if ($package['id'] === $packageId) {
                return $package;
            }
        }

        return null;
    }

    /**
     * Set the PackageService instance.
     * @param PackageService $packageService
     */
    public function setPackageService(PackageService $packageService): void
    {
        $this->packageService = $packageService;
    }

    private function get_releases(string $repo): array
    {
        return call_user_func($this->fetchReleasesCallback, $repo);
    }
}
