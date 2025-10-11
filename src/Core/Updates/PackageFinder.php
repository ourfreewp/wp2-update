<?php

namespace WP2\Update\Core\Updates;

use WP2\Update\Utils\Formatting;
use WP2\Update\Utils\Logger;
use WP2\Update\Core\API\RepositoryService;

/**
 * Scans the installation for themes and plugins that declare an Update URI.
 */
class PackageFinder
{
    private RepositoryService $repositoryService;

    public function __construct(RepositoryService $repositoryService)
    {
        $this->repositoryService = $repositoryService;
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
        $this->ensure_get_plugins_available();

        $managed = [];

        foreach (get_plugins() as $slug => $plugin) {
            $updateUri = $plugin['UpdateURI'] ?? $plugin['Update URI'] ?? '';
            $repo      = Formatting::normalize_repo($updateUri);

            if (!$repo) {
                continue;
            }

            $managed[$slug] = [
                'slug'     => $slug,
                'repo'     => $repo,
                'name'     => $plugin['Name'] ?? $slug,
                'version'  => $plugin['Version'] ?? '0.0.0',
                'type'     => 'plugin',
                'app_slug' => sanitize_title($plugin['Name'] ?? $slug),
            ];
        }

        return apply_filters('wp2_update_managed_plugins', $managed);
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
        $managed = [];

        foreach (wp_get_themes() as $slug => $theme) {
            $updateUri = $theme->get('UpdateURI') ?: $theme->get('Update URI');
            $repo      = Formatting::normalize_repo($updateUri);

            if (!$repo) {
                continue;
            }

            $managed[$slug] = [
                'slug'     => $slug,
                'repo'     => $repo,
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
        // Simulate updating the app record with the assigned packages
        Logger::log('INFO', "Updated managed repositories for app {$appId}: " . json_encode($packages));
    }
}
