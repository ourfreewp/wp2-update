<?php

namespace WP2\Update;

// Admin
use WP2\Update\Admin\Assets;
use WP2\Update\Admin\Data as AdminData;
use WP2\Update\Admin\Menu;
use WP2\Update\Admin\Screens;

// CLI
use WP2\Update\CLI\Commands;

// Database
use WP2\Update\Database\Schema;

// Data
use WP2\Update\Data\AppData;
use WP2\Update\Data\HealthData;
use WP2\Update\Data\PackageData;

// Health
use WP2\Update\Health\Checks\ConnectivityCheck;
use WP2\Update\Health\Checks\DataIntegrityCheck;
use WP2\Update\Health\Checks\DatabaseCheck;
use WP2\Update\Health\Checks\EnvironmentCheck;
use WP2\Update\Health\Checks\RESTCheck;
use WP2\Update\Health\Checks\AssetCheck;

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
use WP2\Update\Utils\Logger;
use WP2\Update\Utils\JWT;

// Webhooks
use WP2\Update\Webhooks\WebhookController;

/**
 * Main bootstrap class for the plugin.
 * Initializes all services and registers hooks.
 */
final class Init {

    private Container $container;

    /**
     * Constructor.
     * Sets up the service container.
     */
    public function __construct() {
        $this->container = new Container();
    }

    /**
     * Kicks off the plugin initialization.
     * This is the main entry point.
     */
    public static function boot(): void {
        // Hook for plugin activation to set up necessary database tables and options.
        register_activation_hook(WP2_UPDATE_PLUGIN_FILE, [self::class, 'activate']);

        $instance = new self();
        $instance->register_services();
        $instance->initialize_hooks();
    }

    /**
     * Handles plugin activation.
     * Creates database tables and generates a security salt.
     */
    public static function activate(): void {
        error_log('Init::activate() called.');
        Schema::create_tables();
        error_log('Schema::create_tables() executed.');

        // Generate a unique salt for encryption if it doesn't exist.
        if (!get_option('wp2_update_encryption_salt')) {
            update_option('wp2_update_encryption_salt', wp_generate_password(64, true, true));
            error_log('Encryption salt generated and saved.');
        } else {
            error_log('Encryption salt already exists.');
        }
    }

    /**
     * Registers all services and their dependencies with the container.
     * This separates service definition from usage.
     */
    public function register_services(): void {
        // --- Utilities ---
        $this->container->register(Encryption::class, fn() => new Encryption(get_option(Config::OPTION_ENCRYPTION_SALT) ?: bin2hex(random_bytes(16))));
        $this->container->register(Logger::class, fn() => new Logger());

        // --- GitHub API Services ---
        $this->container->register(ClientService::class, fn($c) => new ClientService(
            $c->get(JWT::class),
            $c->get(AppData::class),
            $c->get(Encryption::class)
        ));
        $this->container->register(RepositoryService::class, fn($c) => new RepositoryService(
            $c->get(AppData::class),
            $c->get(ClientService::class)
        ));
        $this->container->register(ReleaseService::class, fn($c) => new ReleaseService(
            $c->get(ClientService::class),
            $c->get(AppData::class)
        ));
        $this->container->register(ConnectionService::class, fn() => new ConnectionService());

        // --- Core Application Services ---
        $this->container->register(PackageService::class, fn($c) => new PackageService(
            $c->get(RepositoryService::class),
            $c->get(ReleaseService::class),
            $c->get(ClientService::class),
            $c->get(AppService::class),
            $c->get(ConnectionService::class)
        ));
        $this->container->register(AppService::class, fn($c) => new AppService(
            $c->get(ClientService::class),
            $c->get(RepositoryService::class),
            fn() => $c->get(PackageService::class),
            $c->get(AppData::class),
            $c->get(Encryption::class)
        ));

        // --- Data Access Layer ---
        $this->container->register(AppData::class, fn() => new AppData());
        $this->container->register(PackageData::class, fn($c) => new PackageData($c->get(PackageService::class)));
        $this->container->register(HealthData::class, fn($c) => new HealthData($c->get(ConnectionService::class)));

        // --- WordPress Update Integration ---
        $this->container->register(PluginUpdater::class, fn($c) => new PluginUpdater(
            $c->get(PackageService::class), $c->get(ReleaseService::class), $c->get(ClientService::class), $c->get(RepositoryService::class)
        ));
        $this->container->register(ThemeUpdater::class, fn($c) => new ThemeUpdater(
            $c->get(PackageService::class), $c->get(ReleaseService::class), $c->get(ClientService::class), $c->get(RepositoryService::class)
        ));

        // --- REST API and Webhooks ---
        $this->container->register(Router::class, fn($c) => new Router([
            new AppsController(
                $c->get(ConnectionService::class)
            ),
            new ConnectionController($c->get(ConnectionService::class)),
            new CredentialsController($c->get(ConnectionService::class)), // Note: Check if this is correct dependency
            new HealthController(
                $c->get(ConnectivityCheck::class),
                $c->get(DataIntegrityCheck::class),
                $c->get(EnvironmentCheck::class),
                $c->get(DatabaseCheck::class),
                $c->get(RESTCheck::class), // New Check
                $c->get(AssetCheck::class)  // New Check
            ),
            new LogController(),
            new NonceController(),
            new PackagesController($c->get(PackageService::class)),
        ]));
        $this->container->register(WebhookController::class, fn($c) => new WebhookController(
            $c->get(ConnectionService::class),
            $c->get(ClientService::class),
            $c->get(ReleaseService::class)
        ));

        // --- Admin Area ---
        $this->container->register(AdminData::class, fn($c) => new AdminData(
            $c->get(AppData::class), $c->get(PackageData::class), $c->get(HealthData::class), $c->get(AppService::class)
        ));
        $this->container->register(Screens::class, fn($c) => new Screens(
            $c->get(Router::class)->get_controller(HealthController::class),
            $c->get(AdminData::class),
            $c
        ));
        $this->container->register(Menu::class, fn($c) => new Menu($c->get(Screens::class)));
        $this->container->register(Assets::class, fn($c) => new Assets($c->get(AdminData::class), $c->get(Logger::class)));

        // --- JWT ---
        $this->container->register(JWT::class, fn() => new JWT());

        // --- Health Checks ---
        $this->container->register(ConnectivityCheck::class, fn($c) => new ConnectivityCheck(
            $c->get(ConnectionService::class)
        ));
        $this->container->register(DataIntegrityCheck::class, fn() => new DataIntegrityCheck());
        $this->container->register(EnvironmentCheck::class, fn() => new EnvironmentCheck());
        $this->container->register(DatabaseCheck::class, fn() => new DatabaseCheck());
        $this->container->register(RESTCheck::class, fn() => new RESTCheck());
        $this->container->register(AssetCheck::class, fn() => new AssetCheck());
    }

    /**
     * Initializes all WordPress hooks, pulling constructed services from the container.
     */
    public function initialize_hooks(): void {
        // General Hooks
        $this->schedule_log_pruning();

        // WordPress Update Integration
        $this->container->get(PluginUpdater::class)->register_hooks();
        $this->container->get(ThemeUpdater::class)->register_hooks();

        // REST API & Webhooks
        add_action('rest_api_init', [$this->container->get(Router::class), 'register_routes']);
        add_action('rest_api_init', [$this->container->get(WebhookController::class), 'register_route']);

        // Admin Area
        $this->initialize_admin_hooks();

        // CLI
        if (defined('WP_CLI') && WP_CLI) {
            Commands::register($this->container->get(PackageService::class));
        } else {
            error_log('WP_CLI is not defined during execution.');
        }
    }

    /**
     * Initializes hooks specific to the WordPress admin area.
     */
    private function initialize_admin_hooks(): void {
        if (!is_admin()) {
            return;
        }

        $menu   = $this->container->get(Menu::class);
        $assets = $this->container->get(Assets::class);

        add_action('admin_menu', [$menu, 'register_menu']);
        $assets->register_hooks();
    }

    /**
     * Schedules the weekly cron job for pruning old logs.
     */
    private function schedule_log_pruning(): void {
        if (!wp_next_scheduled('wp2_update_prune_logs')) {
            wp_schedule_event(time(), 'weekly', 'wp2_update_prune_logs');
        }

        add_action('wp2_update_prune_logs', function() {
            $this->container->get(Logger::class)->prune_logs();
        });
    }
}

// Hook into 'plugins_loaded' to ensure all WordPress functions and other plugins are available.
add_action('plugins_loaded', [Init::class, 'boot']);
