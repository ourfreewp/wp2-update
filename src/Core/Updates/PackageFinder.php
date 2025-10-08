<?php

namespace WP2\Update\Core\Updates;

use WP2\Update\Utils\SharedUtils; // Import SharedUtils

/**
 * Scans the installation for themes and plugins that declare an Update URI.
 */
class PackageFinder
{
    public function __construct()
    {
        // Constructor updated to remove unused dependencies
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
            $repo      = SharedUtils::normalize_repo($updateUri); // Updated to use SharedUtils

            if (!$repo) {
                continue;
            }

            $managed[$slug] = [
                'slug'     => $slug,
                'repo'     => $repo,
                'name'     => $plugin['Name'] ?? $slug,
                'version'  => $plugin['Version'] ?? '0.0.0',
                'type'     => 'plugin',
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
            $repo      = SharedUtils::normalize_repo($updateUri); // Updated to use SharedUtils

            if (!$repo) {
                continue;
            }

            $managed[$slug] = [
                'slug'     => $slug,
                'repo'     => $repo,
                'name'     => $theme->get('Name') ?: $slug,
                'version'  => $theme->get('Version') ?: '0.0.0',
                'type'     => 'theme',
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
}
