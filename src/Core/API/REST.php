<?php
namespace WP2\Update\Core\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP2\Update\Core\API\GitHubApp\Init as GitHubApp;
use WP2\Update\Core\Webhooks\Handler as WebhookHandler;
use WP_REST_Request;

/**
 * Handles all REST API route registration and callbacks for the plugin.
 */
final class REST {

	private GitHubApp $github_app;
	private WebhookHandler $webhook_handler;

	public function __construct( GitHubApp $github_app, WebhookHandler $webhook_handler ) {
		$this->github_app     = $github_app;
		$this->webhook_handler = $webhook_handler;
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
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * @return \WP_REST_Response
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
			return rest_ensure_response( [ 'success' => true, 'message' => __( 'Connection test successful!', 'wp2-update' ) ] );
		} else {
			return rest_ensure_response( [ 'success' => false, 'message' => $response['data'] ] );
		}
	}

	/**
	 * @return \WP_REST_Response
	 */
	public function clear_cache_force_check() {
		if ( class_exists( '\\WP2\\Update\\Core\\Updates\\PackageFinder' ) ) {
			( new \WP2\Update\Core\Updates\PackageFinder() )->clear_cache();
		}
		
		delete_site_transient( 'update_themes' );
		delete_site_transient( 'update_plugins' );

		wp_update_themes();
		wp_update_plugins();
		
		return rest_ensure_response( [ 'success' => true, 'message' => __( 'Cache cleared and checks forced.', 'wp2-update' ) ] );
	}
}

