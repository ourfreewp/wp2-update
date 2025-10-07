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
use WP2\Update\Utils\Logger;

/**
 * This is the main orchestrator for the plugin.
 * * It instantiates and wires together all core components. It is a best practice to
 * to have a single main entry point for the plugin, which then loads all other components.
 */
final class Init {
    /**
     * Stores the DI container instance in a static property.
     */
    private static $container;

    /**
     * Returns the DI container instance.
     *
     * @return DIContainer|null
     */
    public static function get_container() {
        return self::$container;
    }

    /**
     * Kicks off the plugin.
     *
     * A static entry point that sets up the entire plugin.
     */
    public static function initialize() {
        try {
            // Initialize the DI container
            self::$container = new DIContainer();

            // Register services
            self::$container->register('GitHubService', fn() => new GitHubService());
            self::$container->register('GitHubApp', fn($c) => new GitHubApp($c->resolve('GitHubService')));
            self::$container->register('SharedUtils', fn($c) => new SharedUtils($c->resolve('GitHubApp'), $c->resolve('GitHubService')));
            self::$container->register('PackageFinder', fn($c) => new PackageFinder($c->resolve('SharedUtils')));
            self::$container->register('Connection', fn($c) => new Connection($c->resolve('PackageFinder')));
            self::$container->register('ModelsInit', fn() => new ModelsInit());
            self::$container->register('WebhookHandler', fn($c) => new WebhookHandler($c->resolve('GitHubApp'), $c->resolve('PackageFinder')));
            self::$container->register('ThemeUpdater', fn($c) => new ThemeUpdater(
                $c->resolve('Connection'),
                $c->resolve('GitHubApp'),
                $c->resolve('SharedUtils'),
                $c->resolve('GitHubService')
            ));
            self::$container->register('PluginUpdater', fn($c) => new PluginUpdater(
                $c->resolve('Connection'),
                $c->resolve('GitHubApp'),
                $c->resolve('SharedUtils'),
                $c->resolve('GitHubService')
            ));
            self::$container->register('RestRouter', fn($c) => new RestRouter(
                $c->resolve('GitHubApp'),
                $c->resolve('WebhookHandler'),
                $c->resolve('TaskScheduler')
            ));
            self::$container->register('TaskScheduler', fn($c) => new TaskScheduler($c->resolve('GitHubService')));

            // Resolve and initialize services
            self::$container->resolve('WebhookHandler');
            self::$container->resolve('ThemeUpdater');
            self::$container->resolve('PluginUpdater');
            self::$container->resolve('RestRouter')->register_routes();
            $task_scheduler = self::$container->resolve('TaskScheduler');
            $task_scheduler->init_hooks();
            $task_scheduler->schedule_recurring_tasks();

            // Pass all dependencies to the Admin orchestrator.
            $admin = new AdminInit(
                self::$container->resolve('Connection'),
                self::$container->resolve('GitHubApp'),
                self::$container->resolve('ThemeUpdater'),
                self::$container->resolve('PluginUpdater'),
                self::$container->resolve('SharedUtils'),
                $task_scheduler,
                self::$container
            );

            // Register all necessary hooks.
            $admin->register_hooks();
            self::$container->resolve('ThemeUpdater')->register_hooks();
            self::$container->resolve('PluginUpdater')->register_hooks();
            self::$container->resolve('ModelsInit')->register();

            // Text domain
            add_action('plugins_loaded', [self::class, 'load_textdomain']);

            // Instantiate the Vite class to enqueue admin assets
            new \WP2\Update\Utils\Vite();
        } catch (\Throwable $e) {
            Logger::error('Initialization failed: ' . $e->getMessage(), 'init');
        }
    }

    /**
     * Loads the plugin's text domain for translation.
     */
    public static function load_textdomain() {
        load_plugin_textdomain( 'wp2-update', false, dirname( WP2_UPDATE_PLUGIN_FILE ) . '/languages' );
    }
}
