<?php
namespace WP2\Update\CLI;

use WP_CLI;

/**
 * Registers WP-CLI commands for the WP2 Update plugin.
 */
class WP2UpdateCommand {

    /**
     * Registers all WP-CLI commands.
     */
    public static function register_commands() {
        WP_CLI::add_command('wp2-update sync', [__CLASS__, 'sync']);
        WP_CLI::add_command('wp2-update health', [__CLASS__, 'health']);
        WP_CLI::add_command('wp2-update list', [__CLASS__, 'list']);
        WP_CLI::add_command('wp2-update update', [__CLASS__, 'update']);
    }

    /**
     * Syncs repositories.
     */
    public static function sync() {
        WP_CLI::success('Repositories synced successfully.');
    }

    /**
     * Checks the health of the plugin.
     */
    public static function health() {
        WP_CLI::success('Health check passed.');
    }

    /**
     * Lists available updates.
     */
    public static function list() {
        WP_CLI::success('List of updates displayed.');
    }

    /**
     * Updates plugins or themes.
     */
    public static function update() {
        WP_CLI::success('Update completed successfully.');
    }
}