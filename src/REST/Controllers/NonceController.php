<?php

namespace WP2\Update\REST\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function current_user_can;
use function wp_create_nonce;
use WP2\Update\Utils\Logger;

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
				'permission_callback' => '__return_true',
				'args'                => [
					'action' => [
						'required' => true,
						'type'     => 'string',
						'validate_callback' => function($param) {
							return !empty($param);
						},
					],
				],
			]
		);
	}

	public function refresh_nonce( WP_REST_Request $request ): WP_REST_Response {
		$action = $request->get_param('action');
		$new_nonce = wp_create_nonce( $action );
		Logger::log('INFO', "Generated new nonce for action '{$action}': {$new_nonce}");

		return $this->respond(
			[
				'nonce' => $new_nonce,
			]
		);
	}
}
