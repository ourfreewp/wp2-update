<?php

namespace WP2\Update;

use WP2\Update\Admin\Init as AdminInit;
use WP2\Update\Core\API\CredentialService;
use WP2\Update\Core\API\GitHubClientFactory;
use WP2\Update\Core\API\ReleaseService;
use WP2\Update\Core\API\RepositoryService;
use WP2\Update\Core\Updates\PackageService;
use WP2\Update\Core\API\ConnectionService;
use WP2\Update\Core\Updates\PluginUpdater;
use WP2\Update\Core\Updates\ThemeUpdater;
use WP2\Update\Core\Updates\PackageFinder;
use WP2\Update\REST\Controllers\ConnectionController;
use WP2\Update\REST\Controllers\CredentialsController;
use WP2\Update\REST\Controllers\PackagesController;
use WP2\Update\REST\Router;
use WP2\Update\Webhook\Controller as WebhookController;
use WP2\Update\Utils\Logger;

/**
 * Main bootstrap class for the plugin.
 */
final class Init {
    /**
     * Entry point called from the plugin loader.
     */
    public static function boot(): void {
        $instance = new self();
        $instance->register_hooks();
    }

    /**
     * Register WordPress hooks for the plugin.
     */
    private function register_hooks(): void {
        add_action('plugins_loaded', [static::class, 'load_textdomain']);
        add_action('init', [$this, 'initialize_services']);
    }

    /**
     * Loads the plugin textdomain for translations.
     */
    public static function load_textdomain(): void {
        load_plugin_textdomain('wp2-update', false, dirname(WP2_UPDATE_PLUGIN_FILE) . '/languages');
    }

    /**
     * Instantiate all services and register their hooks.
     * This acts as a simple dependency injection container.
     */
    public function initialize_services(): void {
        // Core Services (no dependencies or only other core services)
        $credentialService = new CredentialService();
        $clientFactory     = new GitHubClientFactory($credentialService);
        $releaseService    = new ReleaseService($clientFactory);
        $repositoryService = new RepositoryService($clientFactory);
        $connectionService = new ConnectionService($clientFactory, $credentialService);
        $packageFinder     = new PackageFinder();
        $packageService    = new PackageService($repositoryService, $releaseService, $clientFactory, $packageFinder);

        // REST API Controllers
        $credentialsController = new CredentialsController($credentialService);
        $connectionController  = new ConnectionController($connectionService, $credentialService, $packageFinder);
        $packagesController    = new PackagesController($packageService);

        // Routers
        // Ensure the Router class is loaded properly
        if (!class_exists(Router::class)) {
            $router_file = WP2_UPDATE_PLUGIN_DIR . '/src/REST/Router.php';
            if (file_exists($router_file)) {
                require_once $router_file;
            } else {
                Logger::log('CRITICAL', 'REST Router class missing. Expected at: ' . $router_file);
                return;
            }
        }

        // Instantiate the Router
        try {
            $router = new Router($credentialsController, $connectionController, $packagesController);
            add_action('rest_api_init', [$router, 'register_routes']);
        } catch (\Exception $e) {
            Logger::log('CRITICAL', 'Failed to initialize Router: ' . $e->getMessage());
            return;
        }

        // Webhook Controller
        $webhookController = new WebhookController($credentialService);
        add_action('rest_api_init', [$webhookController, 'register_route']);

        // Updaters (Theme & Plugin)
        $pluginUpdater = new PluginUpdater($packageFinder, $releaseService, $clientFactory, $repositoryService);
        $themeUpdater  = new ThemeUpdater($packageFinder, $releaseService, $clientFactory, $repositoryService);
        $pluginUpdater->register_hooks();
        $themeUpdater->register_hooks();

        // Admin Functionality (only loaded in the admin area)
        if (is_admin()) {
            $adminInit = new AdminInit($connectionService, $packageService);
            $adminInit->register_hooks();
        }
    }
}
