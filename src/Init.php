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
        add_action('plugins_loaded', [self::class, 'load_textdomain']);
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
        $connectionController  = new ConnectionController($connectionService);
        $packagesController    = new PackagesController($packageService);

        // Routers
        $router = new Router($credentialsController, $connectionController, $packagesController);
        add_action('rest_api_init', [$router, 'register_routes']);

        // Webhook Controller
        $webhookController = new WebhookController($credentialService);
        add_action('rest_api_init', [$webhookController, 'register_route']);

        // Updaters (Theme & Plugin)
        $pluginUpdater = new PluginUpdater($packageFinder, $releaseService, $clientFactory);
        $themeUpdater  = new ThemeUpdater($packageFinder, $releaseService, $clientFactory);
        $pluginUpdater->register_hooks();
        $themeUpdater->register_hooks();

        // Admin Functionality (only loaded in the admin area)
        if (is_admin()) {
            $adminInit = new AdminInit($connectionService, $packageService);
            $adminInit->register_hooks();
        }
    }
}
