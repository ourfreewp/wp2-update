<?php
namespace WP2\Update\CLI;

use WP_CLI;

if ( ! class_exists( '\WP_CLI' ) ) {
    error_log('WP_CLI is not available. Skipping WP2UpdateCommand registration.');
    return;
}

/**
 * Registers WP-CLI commands for the WP2 Update plugin.
 */
class WP2UpdateCommand {

    /**
     * Registers all WP-CLI commands.
     */
    public static function register_commands() {
        WP_CLI::add_command('wp2-update sync', [__CLASS__, 'sync_repositories']);
        WP_CLI::add_command('wp2-update health', [__CLASS__, 'check_health']);
        WP_CLI::add_command('wp2-update list-updates', [__CLASS__, 'list_available_updates']);
        WP_CLI::add_command('wp2-update update', [__CLASS__, 'update_packages']);
    }

    /**
     * Gets the DI container.
     */
    private static function get_container() {
        $container = apply_filters('wp2_update_di_container', null);
        if (!$container) {
            WP_CLI::error('Could not initialize WP2 Update services.');
            return null;
        }
        return $container;
    }

    /**
     * Syncs repositories from all connected GitHub Apps.
     */
    public static function sync_repositories() {
        $container = self::get_container();
        if (!$container) return;
        
        /** @var \WP2\Update\Core\Tasks\Scheduler $scheduler */
        $scheduler = $container->resolve('TaskScheduler');
        $scheduler->run_sync_all_repos();
        
        WP_CLI::success(__('Repositories synced successfully.', 'wp2-update'));
    }

    /**
     * Checks the health of the plugin's connection to GitHub.
     */
    public static function check_health() {
        $container = self::get_container();
        if (!$container) return;

        /** @var \WP2\Update\Core\API\GitHubApp\Init $github_app */
        $github_app = $container->resolve('GitHubApp');
        $status = $github_app->get_connection_status();

        if ($status['connected']) {
            WP_CLI::success(__('Health check passed. ' . $status['message'], 'wp2-update'));
        } else {
            WP_CLI::warning(__('Health check failed. ' . $status['message'], 'wp2-update'));
        }
    }

    /**
     * Lists available updates for managed themes and plugins.
     */
    public static function list_available_updates() {
        $container = self::get_container();
        if (!$container) return;

        // Force a check to get latest data
        wp_update_themes();
        wp_update_plugins();
        
        /** @var \WP2\Update\Core\Updates\PackageFinder $package_finder */
        $package_finder = $container->resolve('PackageFinder');
        // Need to refetch packages after update checks
        $package_finder->clear_cache();
        
        $themes = $package_finder->get_managed_themes();
        $plugins = $package_finder->get_managed_plugins();
        
        $theme_updates = get_site_transient('update_themes');
        $plugin_updates = get_site_transient('update_plugins');

        $updates = [];
        
        if (!empty($theme_updates->response)) {
            foreach ($theme_updates->response as $slug => $data) {
                if (isset($themes[$slug])) {
                    $current_version = wp_get_theme($slug)->get('Version');
                    $updates[] = [
                        'name' => $themes[$slug]['name'],
                        'type' => 'theme',
                        'slug' => $slug,
                        'version' => $current_version . ' -> ' . $data['new_version'],
                    ];
                }
            }
        }

        if (!empty($plugin_updates->response)) {
            foreach ($plugin_updates->response as $slug => $data) {
                if (isset($plugins[$slug])) {
                    $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $slug);
                    $updates[] = [
                        'name' => $plugins[$slug]['name'],
                        'type' => 'plugin',
                        'slug' => $slug,
                        'version' => $plugin_data['Version'] . ' -> ' . $data->new_version,
                    ];
                }
            }
        }

        if (empty($updates)) {
            WP_CLI::success('No updates available.');
            return;
        }

        WP_CLI\Utils\format_items('table', $updates, ['name', 'type', 'slug', 'version']);
    }

    /**
     * Updates one or more managed plugins or themes.
     *
     * ## OPTIONS
     *
     * [<slug>...]
     * : One or more theme or plugin slugs to update. If not provided, all available updates will be applied.
     *
     * [--all]
     * : If set, update all themes and plugins that have updates available.
     * ---
     */
    public static function update_packages($args, $assoc_args) {
        $container = self::get_container();
        if (!$container) return;

        /** @var \WP2\Update\Core\Updates\ThemeUpdater $theme_updater */
        $theme_updater = $container->resolve('ThemeUpdater');
        /** @var \WP2\Update\Core\Updates\PluginUpdater $plugin_updater */
        $plugin_updater = $container->resolve('PluginUpdater');
        /** @var \WP2\Update\Core\Updates\PackageFinder $package_finder */
        $package_finder = $container->resolve('PackageFinder');

        // Force a check
        wp_update_themes();
        wp_update_plugins();
        $package_finder->clear_cache(); // re-scan packages

        $theme_updates = get_site_transient('update_themes');
        $plugin_updates = get_site_transient('update_plugins');

        $all_updates = [];

        if (!empty($theme_updates->response)) {
            $all_updates = array_merge($all_updates, $theme_updates->response);
        }
        if (!empty($plugin_updates->response)) {
            $all_updates = array_merge($all_updates, (array) $plugin_updates->response);
        }

        if (empty($all_updates)) {
            WP_CLI::success('No updates to apply.');
            return;
        }

        $managed_themes = $package_finder->get_managed_themes();
        $managed_plugins = $package_finder->get_managed_plugins();
        
        $updated_count = 0;
        foreach ($all_updates as $slug => $data) {
            $is_plugin = is_object($data); // plugin data is object, theme is array
            $package_slug = $is_plugin ? $data->plugin : $slug;

            // If specific slugs are provided, only update them
            if (!empty($args) && !in_array($package_slug, $args)) {
                continue;
            }

            if ($is_plugin && isset($managed_plugins[$package_slug])) {
                $item_data = $managed_plugins[$package_slug];
                WP_CLI::log("Updating plugin: {$item_data['name']} to version {$data->new_version}...");
                $result = $plugin_updater->install_plugin($item_data['app_slug'], $item_data['repo'], $data->new_version, $package_slug);
                 if (is_wp_error($result)) {
                    WP_CLI::warning("Failed to update plugin {$item_data['name']}: " . $result->get_error_message());
                } else {
                    WP_CLI::success("Successfully updated plugin {$item_data['name']}.");
                    $updated_count++;
                }

            } elseif (!$is_plugin && isset($managed_themes[$slug])) {
                $item_data = $managed_themes[$slug];
                WP_CLI::log("Updating theme: {$item_data['name']} to version {$data['new_version']}...");
                $result = $theme_updater->install_theme($item_data['app_slug'], $item_data['repo'], $data['new_version'], $slug);
                 if (is_wp_error($result)) {
                    WP_CLI::warning("Failed to update theme {$item_data['name']}: " . $result->get_error_message());
                } else {
                    WP_CLI::success("Successfully updated theme {$item_data['name']}.");
                    $updated_count++;
                }
            }
        }

        if ($updated_count > 0) {
            WP_CLI::success("Finished. {$updated_count} package(s) updated.");
        } else {
            WP_CLI::log('No matching packages were updated.');
        }
    }
}
