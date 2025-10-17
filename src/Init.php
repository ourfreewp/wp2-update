<?php
declare(strict_types=1);

namespace WP2\Update;

// Admin
use WP2\Update\Admin\Assets;
use WP2\Update\Admin\Pages;

// CLI
use WP2\Update\CLI\Commands;

// Data
use WP2\Update\Data\AppData;
use WP2\Update\Data\HealthData;
use WP2\Update\Data\PackageData;

// REST
use WP2\Update\REST\Router;
use WP2\Update\REST\Controllers\AppsController;
use WP2\Update\REST\Controllers\HealthController;
use WP2\Update\REST\Controllers\PackagesController;

// Repositories
use WP2\Update\Repositories\PluginRepository;
use WP2\Update\Repositories\ThemeRepository;

// Services
use WP2\Update\Services\Github\AppService;
use WP2\Update\Services\Github\ClientService;
use WP2\Update\Services\Github\ReleaseService;
use WP2\Update\Services\Github\RepositoryService;
use WP2\Update\Services\PackageService;
use WP2\Update\Services\AppPackageMediator;

// Updates
use WP2\Update\Updates\PluginUpdater;
use WP2\Update\Updates\ThemeUpdater;

// Utils
use WP2\Update\Utils\Encryption;
use WP2\Update\Utils\JWT;
use WP2\Update\Utils\Logger;

// Webhooks
use WP2\Update\Webhooks\WebhookController;

// Health Checks
use WP2\Update\Health\Checks\ConnectivityCheck;
use WP2\Update\Health\Checks\DataIntegrityCheck;
use WP2\Update\Health\Checks\EnvironmentCheck;
use WP2\Update\Health\Checks\DatabaseCheck;
use WP2\Update\Health\Checks\RESTCheck;
use WP2\Update\Health\Checks\AssetCheck;

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
		register_activation_hook( WP2_UPDATE_PLUGIN_FILE, [ self::class, 'activate' ] );

		$instance = new self();
		$instance->register_services();
		$instance->initialize_hooks();
	}

	/**
	 * Handles plugin activation.
	 */
	public static function activate(): void {
		Logger::info( 'Activating plugin.' );

		// Generate a unique salt for encryption if it doesn't exist.
		if ( ! get_option( Config::OPTION_ENCRYPTION_SALT ) ) {
			update_option( Config::OPTION_ENCRYPTION_SALT, wp_generate_password( 64, true, true ) );
			Logger::info( 'Encryption salt generated and saved.' );
		} else {
			Logger::info( 'Encryption salt already exists.' );
		}

		// Flush rewrite rules to ensure REST API routes are registered.
		flush_rewrite_rules();
	}

	/**
	 * Registers all services and their dependencies with the container.
	 * This separates service definition from usage.
	 */
	public function register_services(): void {
		$this->register_utilities();
		$this->register_github_services();
		$this->register_core_services();
		$this->register_data_services();
		$this->register_update_services();
		$this->register_admin_services(); // Added admin services registration
	}

	private function register_utilities(): void {
		$this->container->register(Encryption::class, fn() => new Encryption());
		$this->container->register(JWT::class, fn() => new JWT());
		$this->container->register(Logger::class, fn() => new Logger());
	}

	private function register_github_services(): void {
		$this->container->register( ClientService::class, fn( $c ) => new ClientService(
			$c->get( JWT::class),
			$c->get( AppData::class),
			$c->get( Encryption::class),
			$c->get( Logger::class)
		) );
		$this->container->register( RepositoryService::class, fn( $c ) => new RepositoryService(
			$c->get( AppData::class),
			$c->get( ClientService::class)
		) );
		$this->container->register( ReleaseService::class, fn( $c ) => new ReleaseService(
			$c->get( ClientService::class),
			$c->get( AppData::class)
		) );
	}

	private function register_core_services(): void {
		$this->container->register( PackageService::class, fn( $c ) => new PackageService(
			$c->get( RepositoryService::class),
			$c->get( ReleaseService::class),
			$c->get( ClientService::class),
			$c->get( PluginRepository::class),
			$c->get( ThemeRepository::class),
			$c->get( AppService::class)
		) );
		$this->container->register( AppService::class, fn( $c ) => new AppService(
			$c->get( ClientService::class),
			$c->get( RepositoryService::class),
			fn() => $c->get( PackageService::class),
			$c->get( AppData::class),
			$c->get( Encryption::class)
		) );
	}

	private function register_data_services(): void {
		$this->container->register( AppData::class, fn() => new AppData() );
		$this->container->register( PackageData::class, fn( $c ) => new PackageData( $c->get( PackageService::class) ) );
		$this->container->register( HealthData::class, fn( $c ) => new HealthData(
			$c->get( AppService::class),
			[]
		) );
		$this->container->register( PluginRepository::class, fn() => new PluginRepository() );
		$this->container->register( ThemeRepository::class, fn() => new ThemeRepository() );
		$this->container->register( Router::class, fn( $c ) => new Router([
			$c->get( AppsController::class),
			$c->get( HealthController::class),
			$c->get( PackagesController::class),
		]) );
		$this->container->register( AppsController::class, fn( $c ) => new AppsController(
            $c->get(AppService::class)
        ));
		$this->container->register(ConnectivityCheck::class, fn($c) => new ConnectivityCheck(
            $c->get(AppService::class)
        ));
        $this->container->register(DataIntegrityCheck::class, fn() => new DataIntegrityCheck());
        $this->container->register(EnvironmentCheck::class, fn() => new EnvironmentCheck());
        $this->container->register(DatabaseCheck::class, fn() => new DatabaseCheck());
        $this->container->register(RESTCheck::class, fn() => new RESTCheck());
        $this->container->register(AssetCheck::class, fn() => new AssetCheck());
        $this->container->register(HealthController::class, fn($c) => new HealthController(
            $c->get(ConnectivityCheck::class),
            $c->get(DataIntegrityCheck::class),
            $c->get(EnvironmentCheck::class),
            $c->get(DatabaseCheck::class),
            $c->get(RESTCheck::class),
            $c->get(AssetCheck::class)
        ));
		$this->container->register(PackagesController::class, fn($c) => new PackagesController(
            $c->get(PackageService::class)
        ));
		$this->container->register(WebhookController::class, fn($c) => new WebhookController(
            $c->get(ClientService::class),
            $c->get(ReleaseService::class)
        ));
	}

	private function register_update_services(): void {
		$this->container->register( PluginUpdater::class, fn( $c ) => new PluginUpdater(
			$c->get( PackageService::class),
			$c->get( ReleaseService::class),
			$c->get( ClientService::class),
			$c->get( RepositoryService::class)
		) );
		$this->container->register( ThemeUpdater::class, fn( $c ) => new ThemeUpdater(
			$c->get( PackageService::class),
			$c->get( ReleaseService::class),
			$c->get( ClientService::class),
			$c->get( RepositoryService::class)
		) );
	}

	private function register_admin_services(): void {
        // Register dependencies for HealthController
        $this->container->register(ConnectivityCheck::class, fn() => new ConnectivityCheck(
			$this->container->get(AppService::class)
		));
        $this->container->register(DataIntegrityCheck::class, fn() => new DataIntegrityCheck());
        $this->container->register(EnvironmentCheck::class, fn() => new EnvironmentCheck());
        $this->container->register(DatabaseCheck::class, fn() => new DatabaseCheck());
        $this->container->register(RESTCheck::class, fn() => new RESTCheck());
        $this->container->register(AssetCheck::class, fn() => new AssetCheck());

        $this->container->register(HealthController::class, fn($c) => new HealthController(
            $c->get(ConnectivityCheck::class),
            $c->get(DataIntegrityCheck::class),
            $c->get(EnvironmentCheck::class),
            $c->get(DatabaseCheck::class),
            $c->get(RESTCheck::class),
            $c->get(AssetCheck::class)
        ));

        // Register dependencies for Data
        $this->container->register(AppData::class, fn() => new AppData());
        $this->container->register(PackageData::class, fn($c) => new PackageData($c->get(PackageService::class)));
        $this->container->register(HealthData::class, fn($c) => new HealthData(
            $c->get(AppService::class),
            [
                $c->get(ConnectivityCheck::class),
                $c->get(DataIntegrityCheck::class),
                $c->get(EnvironmentCheck::class),
                $c->get(DatabaseCheck::class),
                $c->get(RESTCheck::class),
                $c->get(AssetCheck::class)
            ]
        ));
        $this->container->register(AppService::class, fn($c) => new AppService(
            $c->get(ClientService::class),
            $c->get(RepositoryService::class),
            fn() => $c->get(PackageService::class),
            $c->get(AppData::class),
            $c->get(Encryption::class)
        ));

        // Modify the Assets registration to no longer depend on the Data class.
        $this->container->register(Assets::class, fn() => new Assets()); // Assets no longer needs dependencies

        $this->container->register(AppPackageMediator::class, fn($c) => new AppPackageMediator(
            $c->get(AppService::class),
            $c->get(PackageService::class)
        ));

        $this->container->get(AppService::class)->setMediator(
            $this->container->get(AppPackageMediator::class)
        );

        $this->container->get(PackageService::class)->setMediator(
            $this->container->get(AppPackageMediator::class)
        );

        $this->container->register(Pages::class, fn() => new Pages());
    }

	/**
	 * Initializes all WordPress hooks, pulling constructed services from the container.
	 */
	public function initialize_hooks(): void {

		// WordPress Update Integration
		$this->container->get( PluginUpdater::class)->register_hooks();
		$this->container->get( ThemeUpdater::class)->register_hooks();

		// REST API & Webhooks
		add_action( 'rest_api_init', [ $this->container->get( Router::class), 'register_routes' ] );
		add_action( 'rest_api_init', [ $this->container->get( WebhookController::class), 'register_route' ] );

		// Admin Area
		$this->initialize_admin_hooks();

		// CLI
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			Commands::register( $this->container->get( PackageService::class) );
		}

		// Register webhook async handler
		add_action( 'wp2_update_handle_webhook', [ $this, 'run_webhook_task' ], 10, 3 );
	}

	/**
	 * Initializes hooks specific to the WordPress admin area.
	 */
	private function initialize_admin_hooks(): void {
		if ( ! is_admin() ) {
			return;
		}

		$assets = $this->container->get( Assets::class);

		$assets->register_hooks();

		$pages = $this->container->get( Pages::class);
		add_action( 'admin_menu', [ $pages, 'register_menu' ] );
	}

	/**
	 * Executes the scheduled webhook task.
	 *
	 * @param string $event The GitHub event name.
	 * @param array  $payload The webhook payload.
	 * @param string $app_id The app ID associated with the webhook.
	 */
	public function run_webhook_task( string $event, array $payload, string $app_id ): void {
		Logger::info('Running webhook task', ['event' => $event, 'app_id' => $app_id]);
		$packageService = $this->container->get( PackageService::class);


		switch ( $event ) {
			case 'push':
				$repoSlug = $payload['repository']['full_name'] ?? null;
				if ( $repoSlug ) {
					$packageService->clear_release_cache( $repoSlug );
				}
				break;

			case 'release':
				$action = $payload['action'] ?? '';
				if ( $action === 'published' ) {
					$repoSlug = $payload['repository']['full_name'] ?? null;
					if ( $repoSlug ) {
						$releaseService = $this->container->get( ReleaseService::class);
						$latestRelease  = $releaseService->get_latest_release( $repoSlug, 'stable' );

						if ( $latestRelease ) {
							$cacheKey = sprintf( Config::TRANSIENT_LATEST_RELEASE, ...explode( '/', $repoSlug ) );
							\WP2\Update\Utils\Cache::set( $cacheKey, $latestRelease, 5 * MINUTE_IN_SECONDS );
						}
					}
				}
				break;

			default:
		}
	}
}

// Hook into 'plugins_loaded' to ensure all WordPress functions and other plugins are available.
add_action( 'plugins_loaded', [ Init::class, 'boot' ] );
