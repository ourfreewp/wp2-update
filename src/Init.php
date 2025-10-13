<?php

namespace WP2\Update;

// Admin
use WP2\Update\Admin\Assets;
use WP2\Update\Admin\Data;
use WP2\Update\Admin\Menu;
use WP2\Update\Admin\Screens;

// CLI
use WP2\Update\CLI\Commands;

// Data
use WP2\Update\Data\ConnectionData;

// Database
use WP2\Update\Database\Schema;

// Health
use WP2\Update\Health\Checks\ConnectivityCheck;
use WP2\Update\Health\Checks\DataIntegrityCheck;
use WP2\Update\Health\Checks\EnvironmentCheck;

// REST
use WP2\Update\REST\Controllers\AppsController;
use WP2\Update\REST\Controllers\ConnectionController;
use WP2\Update\REST\Controllers\CredentialsController;
use WP2\Update\REST\Controllers\HealthController;
use WP2\Update\REST\Controllers\LogController;
use WP2\Update\REST\Controllers\NonceController;
use WP2\Update\REST\Controllers\PackagesController;
use WP2\Update\REST\Router;

// Services
use WP2\Update\Services\Github\AppService;
use WP2\Update\Services\Github\ClientService;
use WP2\Update\Services\Github\ConnectionService;
use WP2\Update\Services\Github\ReleaseService;
use WP2\Update\Services\Github\RepositoryService;
use WP2\Update\Services\PackageService;

// Updates
use WP2\Update\Updates\PluginUpdater;
use WP2\Update\Updates\ThemeUpdater;

// Utils
use WP2\Update\Utils\Encryption;
use WP2\Update\Utils\JWT;
use WP2\Update\Utils\Logger;

// Webhooks
use WP2\Update\Webhooks\WebhookController;

/**
 * Main bootstrap class for the plugin.
 * Initializes all services and registers hooks.
 */
final class Init {

    /**
     * Kicks off the plugin initialization.
     */
    public static function boot(): void {
        $instance = new self();
        // Hook into 'plugins_loaded' to ensure all WordPress functions are available.
        add_action('plugins_loaded', [$instance, 'initialize_services']);
        // Hook for plugin activation to set up necessary database tables.
        register_activation_hook(WP2_UPDATE_PLUGIN_FILE, [Schema::class, 'create_tables']);
    }

    /**
     * Instantiates and wires up all the plugin's services and registers hooks.
     */
    public function initialize_services(): void {
        // --- Low-Level & Utility Services ---
        $encryptionKey = defined('WP2_UPDATE_ENCRYPTION_KEY') ? WP2_UPDATE_ENCRYPTION_KEY : (defined('AUTH_KEY') ? AUTH_KEY : wp_salt());
        $encryptionService = new Encryption($encryptionKey);
        $jwtService = new JWT();
        $logger = new Logger();

        // --- Data Layer ---
        $connectionData = new ConnectionData();

        // --- GitHub Service Layer (inter-dependent) ---
        $clientService = new ClientService($jwtService);
        $repositoryService = new RepositoryService($connectionData, $clientService);
        $connectionService = new ConnectionService($connectionData, $repositoryService, $encryptionService, $jwtService);
        $clientService->setConnectionService($connectionService); // Circular dependency resolution
        $releaseService = new ReleaseService($clientService);

        // --- Application Service Layer ---
        $packageService = new PackageService($repositoryService, $releaseService, $clientService);
        $appService = new AppService($clientService, $connectionService, $repositoryService, $packageService);

        // --- WordPress Update Integration ---
        $pluginUpdater = new PluginUpdater($packageService, $releaseService, $clientService, $repositoryService);
        $themeUpdater = new ThemeUpdater($packageService, $releaseService, $clientService, $repositoryService);
        $pluginUpdater->register_hooks();
        $themeUpdater->register_hooks();

        // --- Presentation Layers ---

        // REST API
        $controllers = [
            new AppsController($connectionService),
            new ConnectionController($connectionService),
            new CredentialsController($connectionService),
            new HealthController(
                new ConnectivityCheck($connectionService),
                new DataIntegrityCheck(),
                new EnvironmentCheck()
            ),
            new LogController(),
            new NonceController(),
            new PackagesController($packageService),
        ];
        $router = new Router($controllers);
        add_action('rest_api_init', [$router, 'register_routes']);

        // Webhooks
        $webhookController = new WebhookController($connectionService);
        add_action('rest_api_init', [$webhookController, 'register_route']);

        // Admin Area
        if (is_admin()) {
            $data = new Data($packageService, $connectionService);
            $healthController = new HealthController(
                new ConnectivityCheck($connectionService),
                new DataIntegrityCheck(),
                new EnvironmentCheck()
            );
            $screens = new Screens($healthController, $data);
            $menu = new Menu($screens);
            $assets = new Assets($data, $logger);

            add_action('admin_menu', [$menu, 'register_menu']);
            $assets->register_hooks();
        }

        // CLI
        if (defined('WP_CLI') && WP_CLI) {
            Commands::register($packageService);
        }
    }
}
