<?php
namespace WP2\Update;

use WP2\Update\Admin\Init as AdminInit;
use WP2\Update\Core\Connection\Init as Connection;
use WP2\Update\Core\GitHubApp\Init as GitHubApp;
use WP2\Update\Core\Updates\PluginUpdater; 
use WP2\Update\Core\Updates\ThemeUpdater;
use WP2\Update\Utils\SharedUtils;
use WP2\Update\Core\API\Service as GitHubService;
use WP2\Update\Core\Updates\PackageFinder;
use WP2\Update\Core\Webhooks\Handler as WebhookHandler;
use WP2\Update\Core\API\REST as RestRouter;
use WP2\Update\Core\Tasks\Scheduler as TaskScheduler;

/**
 * This is the main orchestrator for the plugin.
 * * It instantiates and wires together all core components. It is a best practice to
 * have a single main entry point for the plugin, which then loads all other components.
 */
final class Init {
    /**
     * Kicks off the plugin.
     *
     * A static entry point that sets up the entire plugin.
     */
    public static function initialize() {
        // Instantiate core components in the correct order.
        $github_service = new GitHubService();
        $github_app = new GitHubApp($github_service);
        $package_finder = new PackageFinder();
        $connection = new Connection($package_finder);
        $utils      = new SharedUtils($github_app);
        $webhook_handler = new WebhookHandler($github_app, $package_finder);

        // Instantiate type-specific updaters.
        $theme_updater  = new ThemeUpdater($connection, $github_app, $utils);
        $plugin_updater = new PluginUpdater($connection, $github_app, $utils);
        
        // Instantiate and register the REST API router.
        $rest_router = new RestRouter($github_app, $webhook_handler);
        $rest_router->register_routes();

        // Pass all dependencies to the Admin orchestrator.
        $admin = new AdminInit(
            $connection,
            $github_app,
            $theme_updater,
            $plugin_updater,
            $utils
        );

        // Register all necessary hooks.
        $admin->register_hooks();
        $theme_updater->register_hooks();
        $plugin_updater->register_hooks();

        // Initialize and schedule recurring background tasks.
        TaskScheduler::init();
        TaskScheduler::schedule_recurring_tasks();

        // Text domain
        add_action('plugins_loaded', [self::class, 'load_textdomain']);
    }

    /**
     * Loads the plugin's text domain for translation.
     */
    public static function load_textdomain() {
        load_plugin_textdomain( 'wp2-update', false, dirname( WP2_UPDATE_PLUGIN_FILE ) . '/languages' );
    }
}
