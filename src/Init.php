<?php

namespace WP2\Update;

// Prevent multiple inclusions of this file
if (class_exists(__NAMESPACE__ . '\\Init', false)) {
    return;
}

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
use WP2\Update\Admin\Models\Init as ModelsInit;

/**
 * This is the main orchestrator for the plugin.
 * * It instantiates and wires together all core components. It is a best practice to
 * to have a single main entry point for the plugin, which then loads all other components.
 */
final class Init {
    /**
     * Dependency Injection container instance.
     *
     * @var DIContainer|null
     */
    private static $container = null;

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
        $container->register('SharedUtils', fn($c) => new SharedUtils($c->resolve('GitHubApp')));
        $container->register('PackageFinder', fn($c) => new PackageFinder($c->resolve('SharedUtils')));
        $container->register('Connection', fn($c) => new Connection($c->resolve('PackageFinder')));
        $container->register('ModelsInit', fn() => new ModelsInit());
        $container->register('WebhookHandler', fn($c) => new WebhookHandler($c->resolve('GitHubApp'), $c->resolve('PackageFinder')));
        $container->register('ThemeUpdater', fn($c) => new ThemeUpdater(
            $c->resolve('Connection'),
            $c->resolve('GitHubApp'),
            $c->resolve('SharedUtils'),
            $c->resolve('GitHubService')
        ));
        $container->register('PluginUpdater', fn($c) => new PluginUpdater(
            $c->resolve('Connection'),
            $c->resolve('GitHubApp'),
            $c->resolve('SharedUtils'),
            $c->resolve('GitHubService')
        ));
        $container->register('RestRouter', fn($c) => new RestRouter(
            $c->resolve('GitHubApp'),
            $c->resolve('WebhookHandler'),
            $c->resolve('TaskScheduler')
        ));
        $container->register('TaskScheduler', fn($c) => new TaskScheduler($c->resolve('GitHubService')));

        // Pass the container instance via a filter so it can be used elsewhere.
        add_filter('wp2_update_di_container', fn() => $container);

        // Debugging filter execution order
        error_log('src/Init.php: Adding wp2_update_di_container filter.');

        // Resolve and initialize services
        $container->resolve('WebhookHandler');
        $container->resolve('ThemeUpdater');
        $container->resolve('PluginUpdater');
        $container->resolve('RestRouter')->register_routes();
        $task_scheduler = $container->resolve('TaskScheduler');
        $task_scheduler->init_hooks();
        $task_scheduler->schedule_recurring_tasks();

        // Pass all dependencies to the Admin orchestrator.
        $admin = new AdminInit(
            $container->resolve('Connection'),
            $container->resolve('GitHubApp'),
            $container->resolve('ThemeUpdater'),
            $container->resolve('PluginUpdater'),
            $container->resolve('SharedUtils'),
            $task_scheduler
        );

        // Register all necessary hooks.
        $admin->register_hooks();
        $container->resolve('ThemeUpdater')->register_hooks();
        $container->resolve('PluginUpdater')->register_hooks();
        $container->resolve('ModelsInit')->register();

        // Text domain
        add_action('plugins_loaded', [self::class, 'load_textdomain']);

        // Debugging service resolution
        $container->debug_resolve('WebhookHandler');
        $container->debug_resolve('ThemeUpdater');
        $container->debug_resolve('PluginUpdater');
        $container->debug_resolve('RestRouter');
        $container->debug_resolve('TaskScheduler');
    }

    /**
     * Loads the plugin's text domain for translation.
     */
    public static function load_textdomain() {
        load_plugin_textdomain( 'wp2-update', false, dirname( WP2_UPDATE_PLUGIN_FILE ) . '/languages' );
    }

    /**
     * Returns the DI container instance.
     *
     * @return DIContainer|null
     */
    public static function get_container() {
        return self::$container;
    }
}
