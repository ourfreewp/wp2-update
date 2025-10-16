<?php

namespace WP2\Update\CLI;

use WP_CLI;
use WP2\Update\Services\PackageService;
use WP2\Update\Utils\Logger;
use WP2\Update\Config;

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
        WP_CLI::add_command(Config::TEXT_DOMAIN, new self($packageService));
    }

    /**
     * Forces a synchronization of all packages, including unlinked ones.
     * Optionally applies updates to managed packages.
     *
     * ## OPTIONS
     *
     * [--apply-updates]
     * : Automatically apply updates to managed packages.
     *
     * ## EXAMPLES
     *
     * wp wp2-update sync --apply-updates
     *
     * @when after_wp_load
     */
    public function sync(array $args, array $assoc_args): void {
        $apply_updates = isset($assoc_args['apply-updates']);
        Logger::info('Package sync initiated via WP-CLI.', ['apply_updates' => $apply_updates]);

        WP_CLI::line('Starting package synchronization...');
        try {
            $packages = $this->packageService->get_all_packages();
            $total_count = count($packages);

            WP_CLI::success("Synchronization complete. Found {$total_count} packages.");

            if (!empty($packages)) {
                WP_CLI::line('The following packages were found:');
                $items = array_map(function($pkg) {
                    return ['name' => $pkg['name'], 'repo' => $pkg['repo'], 'status' => $pkg['status']];
                }, $packages);
                WP_CLI::table($items, ['name', 'repo', 'status']);
            }

            if ($apply_updates) {
                WP_CLI::line('Applying updates to managed packages...');
                foreach ($packages as $package) {
                    $success = $this->packageService->update_package($package['repo']);
                    if ($success) {
                        WP_CLI::success("Package '{$package['name']}' updated successfully.");
                    } else {
                        WP_CLI::warning("Failed to update package '{$package['name']}'. Check logs for details.");
                    }
                }
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
        Logger::info('Package update initiated via WP-CLI.', ['repo_slug' => $repo_slug]);

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
        Logger::info('Package rollback initiated via WP-CLI.', ['repo_slug' => $repo_slug, 'version' => $version]);

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
}
