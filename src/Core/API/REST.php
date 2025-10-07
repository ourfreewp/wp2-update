<?php
namespace WP2\Update\Core\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP2\Update\Core\API\GitHubApp\Init as GitHubApp;
use WP2\Update\Core\Webhooks\Handler as WebhookHandler;
use WP_REST_Request;
use WP2\Update\Utils\Logger;
use WP2\Update\Core\Tasks\Scheduler;

/**
 * Handles all REST API route registration and callbacks for the plugin.
 */
final class REST {

	private GitHubApp $github_app;
	private WebhookHandler $webhook_handler;
	private Scheduler $scheduler;

	public function __construct( GitHubApp $github_app, WebhookHandler $webhook_handler, Scheduler $scheduler ) {
		$this->github_app     = $github_app;
		$this->webhook_handler = $webhook_handler;
		$this->scheduler       = $scheduler;
	}

	/**
	 * Registers all REST API routes.
	 */
	public function register_routes(): void {
		add_action( 'rest_api_init', [ $this, 'setup_routes' ] );
	}

	/**
	 * Defines the API endpoints.
	 */
	public function setup_routes(): void {
		$permission_callback = static function (): bool {
			return current_user_can( 'manage_options' );
		};

		register_rest_route(
			'wp2-update/v1',
			'/connection-status',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_connection_status' ],
				'permission_callback' => $permission_callback,
			]
		);

		register_rest_route(
			'wp2-update/v1',
			'/test-connection',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'test_connection' ],
				'permission_callback' => $permission_callback,
			]
		);

		register_rest_route(
			'wp2-update/v1',
			'/clear-cache-force-check',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'clear_cache_force_check' ],
				'permission_callback' => $permission_callback,
			]
		);

		register_rest_route(
			'wp2-update/v1',
			'/github/webhooks',
			[
				'methods'             => 'POST',
				'callback'            => [ $this->webhook_handler, 'handle_webhook' ],
				'permission_callback' => '__return_true', // Security is handled within the callback using HMAC signature validation.
			]
		);

		register_rest_route(
			'wp2-update/v1',
			'/debug-app',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'debug_app' ],
				'permission_callback' => $permission_callback,
				'args'                => [
					'app_id' => [
						'required'          => true,
						'validate_callback' => static fn( $param ): bool => is_numeric( $param ),
						'description'       => 'The App ID to debug.',
					],
					'installation_id' => [
						'required'          => true,
						'validate_callback' => static fn( $param ): bool => is_numeric( $param ),
						'description'       => 'The Installation ID to debug.',
					],
				],
			]
		);

		register_rest_route(
			'wp2-update/v1',
			'/settings',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_settings' ],
				'permission_callback' => $permission_callback,
			]
		);

		register_rest_route(
			'wp2-update/v1',
			'/manual-sync',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_manual_sync' ],
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
			]
		);
	}

	/**
	 * Retrieves the connection status of the GitHub app.
	 *
	 * @return \WP_REST_Response The REST response containing the connection status.
	 */
	public function get_connection_status() {
		$status = $this->github_app->get_connection_status();
		return rest_ensure_response( $status );
	}

	/**
	 * @param WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function test_connection( WP_REST_Request $request ) {
		$app_slug = sanitize_text_field( $request->get_param( 'app_slug' ) ?? '' );
		if ( '' === $app_slug ) {
			return rest_ensure_response( [ 'success' => false, 'message' => __( 'No app slug provided.', 'wp2-update' ) ] );
		}

		$response = $this->github_app->test_connection( $app_slug );

		if ( $response['success'] ) {
			return rest_ensure_response( [
				'success' => true,
				'message' => __( 'Connection test successful!', 'wp2-update' ),
				'data'    => $response['data'],
			] );
		} else {
			return rest_ensure_response( [
				'success' => false,
				'message' => $response['data'] ?? __( 'Unknown error occurred.', 'wp2-update' ),
			] );
		}
	}

	/**
	 * @return \WP_REST_Response
	 */
	public function clear_cache_force_check() {
		$container = apply_filters( 'wp2_update_di_container', null );
		if ( $container && method_exists( $container, 'resolve' ) ) {
			$package_finder = $container->resolve( 'PackageFinder' );
			if ( $package_finder instanceof \WP2\Update\Core\Updates\PackageFinder ) {
				$package_finder->clear_cache();
			}
		}

		delete_site_transient( 'update_themes' );
		delete_site_transient( 'update_plugins' );

		wp_update_themes();
		wp_update_plugins();

		return rest_ensure_response( [ 'success' => true, 'message' => __( 'Cache cleared and checks forced.', 'wp2-update' ) ] );
	}

	/**
	 * Debug action for testing connections.
	 *
	 * @param WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function debug_app( WP_REST_Request $request ) {
		$app_id          = (string) $request->get_param( 'app_id' );
		$installation_id = (int) $request->get_param( 'installation_id' );

		try {
			$response = $this->github_app->test_installation_connection( $app_id, $installation_id );
		} catch ( \Throwable $exception ) {
			return rest_ensure_response([
				'success' => false,
				'data'    => [
					'github' => 'Exception occurred: ' . $exception->getMessage(),
				],
			]);
		}

		$success      = ! empty( $response['success'] );
		$error_message = $response['error'] ?? __( 'Unable to connect to GitHub.', 'wp2-update' );

		$data = [
			'github' => $success
				? sprintf( __( 'Successfully connected to GitHub for App ID %s.', 'wp2-update' ), $app_id )
				: $error_message,
		];

		// Ensure all code paths return a value.
		return rest_ensure_response([
			'success' => $success,
			'data'    => $data,
		]);
	}

	/**
	 * Retrieves the plugin settings.
	 *
	 * @return \WP_REST_Response The REST response containing the settings.
	 */
	public function get_settings() {
		$settings = [
			'root'         => esc_url_raw( rest_url() ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'app_name'     => $this->github_app->get_app_name(),
			'callback_url' => $this->github_app->get_callback_url(),
			'webhook_url'  => $this->github_app->get_webhook_url(),
			'github_url'   => $this->github_app->get_github_url(),
		];

		return rest_ensure_response( $settings );
	}

	/**
	 * Handles manual sync requests.
	 *
	 * @param WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function handle_manual_sync( WP_REST_Request $request ) {
		// Implement the manual sync logic here.
		try {
			Logger::log('Manual sync triggered via REST API.', 'info', 'manual-sync');

			// Assuming $this->scheduler is available and properly initialized
			$this->scheduler->run_sync_all_repos();

			return rest_ensure_response([
				'success' => true,
				'message' => __( 'Manual sync completed successfully.', 'wp2-update' ),
			]);
		} catch (\Throwable $exception) {
			Logger::log('Manual sync failed: ' . $exception->getMessage(), 'error', 'manual-sync');

			return rest_ensure_response([
				'success' => false,
				'message' => __( 'Manual sync failed. Check the logs for details.', 'wp2-update' ),
			]);
		}
	}
}
