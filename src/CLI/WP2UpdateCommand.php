<?php
namespace WP2\Update\CLI;

if ( ! class_exists( 'WP_CLI' ) ) {
    class WP_CLI {
        public static function add_command( $name, $callback ) {}
        public static function success( $message ) {}
        public static function log( $message ) {
            echo "LOG: $message\n";
        }
        public static function error( $message ) {
            echo "ERROR: $message\n";
        }
    }
}

/**
 * Registers WP-CLI commands for the WP2 Update plugin.
 */
class WP2UpdateCommand {

    /**
     * Registers all WP-CLI commands.
     */
    public static function register_commands() {
        if ( ! class_exists( 'WP_CLI' ) ) {
            return;
        }

        WP_CLI::add_command('wp2-update sync', [__CLASS__, 'sync']);
        WP_CLI::add_command('wp2-update health', [__CLASS__, 'health']);
        WP_CLI::add_command('wp2-update list', [__CLASS__, 'list']);
        WP_CLI::add_command('wp2-update update', [__CLASS__, 'update']);
    }

    /**
     * Syncs repositories.
     */
    public static function sync() {
        $repos_sync = new \WP2\Update\Core\Sync\Repos(new \WP2\Update\Core\API\Service());
        $repos_sync->run();
        WP_CLI::success('Repositories synced successfully.');
    }

    /**
     * Checks the health of the plugin.
     */
    public static function health() {
        // Perform basic health checks.
        $errors = [];

        // Check if the GitHub Service client is available.
        $github_service = new \WP2\Update\Core\API\Service();
        $client = $github_service->get_client('default_app_slug'); // Replace with actual app slug if needed.
        if (!$client) {
            $errors[] = 'GitHub Service client could not be authenticated.';
        }

        // Check if required constants are defined.
        if (!defined('WP2_UPDATE_PLUGIN_DIR')) {
            $errors[] = 'WP2_UPDATE_PLUGIN_DIR is not defined.';
        }

        if (empty($errors)) {
            WP_CLI::success('Health check passed.');
        } else {
            foreach ($errors as $error) {
                WP_CLI::log($error); // Use log instead of error for now.
            }
        }
    }

    /**
     * Lists available updates.
     */
    public static function list() {
        $updates = \WP2\Update\Core\Updates\PackageFinder::get_available_updates();
        if (empty($updates)) {
            WP_CLI::success('No updates available.');
            return;
        }

        WP_CLI::log('Available updates:');
        foreach ($updates as $package => $version) {
            WP_CLI::log("{$package}: {$version}");
        }
    }

    /**
     * Updates plugins or themes.
     */
    public static function update() {
        $package_finder = new \WP2\Update\Core\Updates\PackageFinder(new \WP2\Update\Utils\SharedUtils(new \WP2\Update\Core\API\GitHubApp\Init(new \WP2\Update\Core\API\Service())));
        $items_to_update = $package_finder->get_items_to_update();

        if (empty($items_to_update)) {
            WP_CLI::log('No items to update.');
            return;
        }

        $connection = new \WP2\Update\Core\Connection\Init($package_finder);
        $github_app = new \WP2\Update\Core\API\GitHubApp\Init(new \WP2\Update\Core\API\Service());
        $utils = new \WP2\Update\Utils\SharedUtils($github_app);

        foreach ($items_to_update as $item) {
            if ($item['type'] === 'theme') {
                $updater = new \WP2\Update\Core\Updates\ThemeUpdater($connection, $github_app, $utils);
                $updater->install_theme($item['app_slug'], $item['repo'], $item['version']);
            } elseif ($item['type'] === 'plugin') {
                $updater = new \WP2\Update\Core\Updates\PluginUpdater($connection, $github_app, $utils);
                $updater->install_plugin($item['app_slug'], $item['repo'], $item['version']);
            } else {
                WP_CLI::log("Unknown item type: {$item['type']}");
                continue;
            }

            WP_CLI::log("Updating {$item['name']}...");
        }

        WP_CLI::success('All updates completed successfully.');
    }
}