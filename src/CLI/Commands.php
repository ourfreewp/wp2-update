<?php

namespace WP2\Update\CLI;

use WP_CLI;
use WP2\Update\Services\PackageService;

/**
 * Handles WP-CLI commands for the WP2 Update plugin.
 */
class Commands extends \WP_CLI_Command {

    private PackageService $packageService;

    public function __construct(PackageService $packageService) {
        $this->packageService = $packageService;
    }

    /**
     * Registers all WP-CLI commands for this plugin.
     * @param PackageService $packageService The instantiated package service.
     */
    public static function register(PackageService $packageService): void {
        WP_CLI::add_command(\WP2\Update\Config::TEXT_DOMAIN, new self($packageService));
    }

    /**
     * Forces a synchronization of all managed packages with their GitHub repositories.
     *
     * ## EXAMPLES
     *
     * wp wp2-update sync
     *
     * @when after_wp_load
     */
    public function sync(): void {
        WP_CLI::line('Starting package synchronization...');
        try {
            $result = $this->packageService->get_all_packages_grouped();
            $managed_count = count($result['managed']);
            $unlinked_count = count($result['unlinked']);

            WP_CLI::success("Synchronization complete. Found {$managed_count} managed and {$unlinked_count} unlinked packages.");

            if (!empty($result['unlinked'])) {
                WP_CLI::warning('The following packages have a GitHub Update URI but could not be matched to a repository in your connected apps:');
                $unlinked_items = array_map(function($pkg) {
                    return ['name' => $pkg['name'], 'repo' => $pkg['repo']];
                }, $result['unlinked']);
                WP_CLI\Utils\format_items('table', $unlinked_items, ['name', 'repo']);
            }
        } catch (\Exception $e) {
            WP_CLI::error('Failed to sync packages: ' . $e->getMessage());
        }
    }

    /**
     * Updates a specific package to the latest available release.
     *
     * ## OPTIONS
     *
     * <repo_slug>
     * : The repository slug of the package to update (e.g., 'owner/repo').
     *
     * ## EXAMPLES
     *
     * wp wp2-update update owner/my-plugin
     *
     * @when after_wp_load
     */
    public function update(array $args): void {
        [$repo_slug] = $args;
        WP_CLI::line("Attempting to update package: {$repo_slug}");

        $success = $this->packageService->update_package($repo_slug);

        if ($success) {
            WP_CLI::success("Package '{$repo_slug}' updated successfully.");
        } else {
            WP_CLI::error("Failed to update package '{$repo_slug}'. Check logs for details.");
        }
    }

    /**
     * Rolls back a package to a specific version.
     *
     * ## OPTIONS
     *
     * <repo_slug>
     * : The repository slug of the package to roll back (e.g., 'owner/repo').
     *
     * --version=<version>
     * : The exact version tag to roll back to (e.g., '1.2.0').
     *
     * ## EXAMPLES
     *
     * wp wp2-update rollback owner/my-plugin --version=1.1.0
     *
     * @when after_wp_load
     */
    public function rollback(array $args, array $assoc_args): void {
        [$repo_slug] = $args;
        $version = $assoc_args['version'] ?? null;

        if (!$version) {
            WP_CLI::error('The --version flag is required for rollback.');
            return;
        }

        WP_CLI::line("Attempting to roll back '{$repo_slug}' to version '{$version}'...");

        $success = $this->packageService->rollback_package($repo_slug, $version);

        if ($success) {
            WP_CLI::success("Package '{$repo_slug}' was successfully rolled back to version '{$version}'.");
        } else {
            WP_CLI::error("Failed to roll back package '{$repo_slug}'. Check logs for details.");
        }
    }

    /**
     * Clears all logs from the WP2 Update log table.
     *
     * ## EXAMPLES
     *
     * wp wp2-update clear_logs
     *
     * @when after_wp_load
     */
    public function clear_logs(): void {
        global $wpdb;
        $table_name = $wpdb->prefix . \WP2\Update\Config::LOGS_TABLE_NAME;

        WP_CLI::confirm('Are you sure you want to clear all WP2 Update logs?');

        $rows_deleted = $wpdb->query("TRUNCATE TABLE {$table_name}");

        if ($rows_deleted === false) {
            WP_CLI::error('Failed to clear logs.');
        } else {
            WP_CLI::success("All logs have been cleared successfully.");
        }
    }
}
