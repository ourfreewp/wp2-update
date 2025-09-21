<?php
namespace WP2\Update\Core\API\GitHubApp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP2\Update\Core\API\Service as GitHubService;
use WP2\Update\Utils\Logger;

/**
 * Acts as a facade for all GitHub App-related operations, containing the
 * high-level business logic for the plugin's interactions with GitHub.
 */
final class Init {

	private GitHubService $service;

	public function __construct( GitHubService $service ) {
		$this->service = $service;
	}

	/**
	 * @param string               $app_slug
	 * @param string               $method
	 * @param string               $path
	 * @param array<string,mixed>  $params
	 * @return array<string,mixed>
	 */
	public function gh( string $app_slug, string $method, string $path, array $params = [] ): array {
		return $this->service->call( $app_slug, $method, $path, $params );
	}

	/**
	 * @return array{connected:bool,message:string}
	 */
	public function get_connection_status(): array {
		$query = new \WP_Query(
			[
				'post_type'      => 'wp2_github_app',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'meta_key'       => '_health_status',
				'meta_value'     => 'ok',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);

		if ( $query->have_posts() ) {
			return [ 'connected' => true, 'message' => __( 'Successfully connected to GitHub.', 'wp2-update' ) ];
		}

		$any_apps = new \WP_Query(
			[
				'post_type'      => 'wp2_github_app',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);
		if ( ! $any_apps->have_posts() ) {
			return [ 'connected' => false, 'message' => __( 'No GitHub Apps configured.', 'wp2-update' ) ];
		}

		return [ 'connected' => false, 'message' => __( 'No healthy GitHub Apps found. Check Settings > System Health for details.', 'wp2-update' ) ];
	}

	/**
	 * @param string $app_slug
	 * @return array{success:bool,data:mixed}
	 */
	public function test_connection( string $app_slug ): array {
		Logger::log( "Testing connection for app: {$app_slug}", 'info', 'connection-test' );
		
		$response = $this->gh( $app_slug, 'GET', '/app' );

		if ( $response['ok'] ) {
			Logger::log( "Connection test successful for app: {$app_slug}", 'success', 'connection-test' );
			return [ 'success' => true, 'data' => $response['data'] ];
		}

		$error_message = $response['error'] ?? __( 'Unknown error.', 'wp2-update' );
		Logger::log( "Connection test failed for app: {$app_slug}. Error: " . $error_message, 'error', 'connection-test' );
		return [ 'success' => false, 'data' => $error_message ];
	}

	/**
	 * @return array<string,string>
	 */
	public function get_installation_requirements(): array {
		$app_post = get_posts(
			[
				'post_type'      => 'wp2_github_app',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
			]
		);

		$app_id = isset( $app_post[0] ) ? (int) $app_post[0]->ID : 0;

		return [
			'App ID'          => get_post_meta( $app_id, '_wp2_app_id', true ) ?: __( 'Not set', 'wp2-update' ),
			'Installation ID' => get_post_meta( $app_id, '_wp2_installation_id', true ) ?: __( 'Not set', 'wp2-update' ),
			'Private Key'     => get_post_meta( $app_id, '_wp2_private_key_content', true ) ? __( 'Set', 'wp2-update' ) : __( 'Not set', 'wp2-update' ),
			'Webhook Secret'  => get_post_meta( $app_id, '_wp2_webhook_secret', true ) ? __( 'Set', 'wp2-update' ) : __( 'Not set', 'wp2-update' ),
		];
	}
}

