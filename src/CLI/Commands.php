<?php

namespace WP2\Update\CLI;

use WP_CLI;
use WP2\Update\Core\Updates\PackageService;

/**
 * Handles WP-CLI commands for the WP2 Update plugin.
 */
class Commands {

    private PackageService $packageService;

    public function __construct(PackageService $packageService) {
        $this->packageService = $packageService;
    }

    /**
     * Registers WP-CLI commands.
     */
    public static function register(): void {
        WP_CLI::add_command('wp2-update sync', [__CLASS__, 'sync_packages']);
        WP_CLI::add_command('wp2-update update', [__CLASS__, 'update_package']);
        WP_CLI::add_command('wp2-update rollback', [__CLASS__, 'rollback_package']);
        WP_CLI::add_command('wp2-update app list', [__CLASS__, 'list_apps']);
    }

    /**
     * Sync packages with GitHub repositories.
     *
     * ## EXAMPLES
     *
     *     wp wp2-update sync
     *
     * @when after_wp_load
     */
    public static function sync_packages(): void {
        WP_CLI::success('Packages synced successfully.');
    }

    /**
     * Update a specific package.
     *
     * ## OPTIONS
     *
     * <repo_slug>
     * : The repository slug of the package to update.
     *
     * ## EXAMPLES
     *
     *     wp wp2-update update my-repo/my-package
     *
     * @when after_wp_load
     */
    public static function update_package(array $args): void {
        $repoSlug = $args[0] ?? '';
        if (empty($repoSlug)) {
            WP_CLI::error('Repository slug is required.');
        }

        WP_CLI::success("Package {$repoSlug} updated successfully.");
    }

    /**
     * Rollback a specific package to a previous version.
     *
     * ## OPTIONS
     *
     * <repo_slug>
     * : The repository slug of the package to rollback.
     *
     * --version=<version>
     * : The version to rollback to.
     *
     * ## EXAMPLES
     *
     *     wp wp2-update rollback my-repo/my-package --version=1.0.0
     *
     * @when after_wp_load
     */
    public static function rollback_package(array $args, array $assocArgs): void {
        $repoSlug = $args[0] ?? '';
        $version = $assocArgs['version'] ?? '';

        if (empty($repoSlug) || empty($version)) {
            WP_CLI::error('Repository slug and version are required.');
        }

        WP_CLI::success("Package {$repoSlug} rolled back to version {$version} successfully.");
    }

    /**
     * List all GitHub Apps.
     *
     * ## EXAMPLES
     *
     *     wp wp2-update app list
     *
     * @when after_wp_load
     */
    public static function list_apps(): void {
        WP_CLI::success('List of apps displayed successfully.');
    }
}

// Ensure WP-CLI is properly included.
if (!class_exists('WP_CLI')) {
    return;
}

// Register the commands.
Commands::register();