<?php

namespace WP2\Update\REST\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function current_user_can;
use function wp_create_nonce;

/**
 * Exposes helper endpoints that deal with security tokens and nonces.
 */
final class NonceController extends AbstractRestController {
	/**
	 * {@inheritdoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/refresh-nonce',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'refresh_nonce' ],
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			]
		);
	}

	public function refresh_nonce( WP_REST_Request $request ): WP_REST_Response {
		return $this->respond(
			[
				'nonce' => wp_create_nonce( 'wp_rest' ),
			]
		);
	}
}
