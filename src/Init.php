<?php

namespace WP2\Update;

use WP2\Update\Admin\Init as AdminInit;
use WP2\Update\Core\API\Service as GitHubService;
use WP2\Update\Core\Updates\PackageFinder;
use WP2\Update\Utils\SharedUtils;
use WP2\Update\Core\API\CredentialService;
use WP2\Update\Core\API\GitHubClientFactory;
use WP2\Update\Core\API\ReleaseService;
use WP2\Update\Core\API\RepositoryService;
use WP2\Update\Core\Updates\PackageService;
use WP2\Update\Core\API\ConnectionService;

// Prevent multiple inclusions of this file.
if (class_exists(__NAMESPACE__ . '\\Init', false)) {
    return;
}

/**
 * Main bootstrap class for the plugin.
 */
final class Init
{
    /**
     * Entry point called from the plugin loader.
     */
    public static function boot(): void
    {
        $instance = new self();
        $instance->register();
    }

    public function __construct()
    {
        // Constructor can be used for dependency injection if needed in the future.
    }

    /**
     * Register WordPress hooks for the plugin.
     */
    private function register(): void
    {
        // Load the text domain for translations.
        add_action('plugins_loaded', [self::class, 'load_textdomain']);

        // Initialize core components.
        add_action('init', [$this, 'initialize_components']);
    }

    /**
     * Loads the plugin textdomain for translations.
     */
    public static function load_textdomain(): void
    {
        load_plugin_textdomain('wp2-update', false, dirname(WP2_UPDATE_PLUGIN_FILE) . '/languages');
    }

    /**
     * Initialize core components of the plugin.
     */
    public function initialize_components(): void
    {
        $credentialService = new CredentialService();
        $clientFactory = new GitHubClientFactory($credentialService);
        $releaseService = new ReleaseService($clientFactory);
        $repositoryService = new RepositoryService($clientFactory);
        $connectionService = new ConnectionService($clientFactory, $credentialService);
        $sharedUtils = new SharedUtils();
        $packageService = new PackageService($repositoryService, $releaseService, $sharedUtils, $clientFactory);

        // Instantiate controllers with their dependencies
        $credentialsController = new \WP2\Update\Rest\Controllers\CredentialsController($credentialService);
        $connectionController = new \WP2\Update\Rest\Controllers\ConnectionController($connectionService);
        $packagesController = new \WP2\Update\Rest\Controllers\PackagesController($packageService);

        // Pass the controller instances to the router
        $router = new \WP2\Update\Rest\Router($credentialsController, $connectionController, $packagesController);
        $router->register_routes();

        // Initialize admin functionality.
        if (is_admin()) {
            $adminInit = new AdminInit($connectionService, $packageService);
            $adminInit->register_hooks();
        }
    }
}
