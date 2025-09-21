<?php
namespace WP2\Update;

use WP2\Update\Admin\Init as AdminInit;
use WP2\Update\Core\Connection\Init as Connection;
use WP2\Update\Core\API\GitHubApp\Init as GitHubApp;
use WP2\Update\Core\Updates\PluginUpdater; 
use WP2\Update\Core\Updates\ThemeUpdater;
use WP2\Update\Utils\SharedUtils;
use WP2\Update\Core\API\Service as GitHubService;
use WP2\Update\Core\Updates\PackageFinder;
use WP2\Update\Core\Webhooks\Handler as WebhookHandler;
use WP2\Update\Core\API\REST as RestRouter;
use WP2\Update\Core\Tasks\Scheduler as TaskScheduler;
use WP2\Update\Utils\DIContainer;

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
        // Initialize the DI container
        $container = new DIContainer();

        // Register services
        $container->register('GitHubService', fn() => new GitHubService());
        $container->register('GitHubApp', fn($c) => new GitHubApp($c->resolve('GitHubService')));
        $container->register('PackageFinder', fn() => new PackageFinder());
        $container->register('Connection', fn($c) => new Connection($c->resolve('PackageFinder')));
        $container->register('SharedUtils', fn($c) => new SharedUtils($c->resolve('GitHubApp')));
        $container->register('WebhookHandler', fn($c) => new WebhookHandler($c->resolve('GitHubApp'), $c->resolve('PackageFinder')));
        $container->register('ThemeUpdater', fn($c) => new ThemeUpdater($c->resolve('Connection'), $c->resolve('GitHubApp'), $c->resolve('SharedUtils')));
        $container->register('PluginUpdater', fn($c) => new PluginUpdater($c->resolve('Connection'), $c->resolve('GitHubApp'), $c->resolve('SharedUtils')));

        // Resolve and initialize services
        $container->resolve('WebhookHandler');
        $container->resolve('ThemeUpdater');
        $container->resolve('PluginUpdater');

        // Instantiate and register the REST API router.
        $rest_router = new RestRouter($container->resolve('GitHubApp'), $container->resolve('WebhookHandler'));
        $rest_router->register_routes();

        // Register BackupEndpoints
        \WP2\Update\Core\API\BackupEndpoints::init();

        // Pass all dependencies to the Admin orchestrator.
        $admin = new AdminInit(
            $container->resolve('Connection'),
            $container->resolve('GitHubApp'),
            $container->resolve('ThemeUpdater'),
            $container->resolve('PluginUpdater'),
            $container->resolve('SharedUtils'),
            $container->resolve('GitHubService') // Added missing GitHubService dependency
        );

        // Register all necessary hooks.
        $admin->register_hooks();
        $container->resolve('ThemeUpdater')->register_hooks();
        $container->resolve('PluginUpdater')->register_hooks();

        // Initialize and schedule recurring background tasks.
        $scheduler = new TaskScheduler($container->resolve('GitHubService')); // Instantiate Scheduler
        $scheduler->init_hooks(); // Register hooks using instance methods
        $scheduler->schedule_recurring_tasks();

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
