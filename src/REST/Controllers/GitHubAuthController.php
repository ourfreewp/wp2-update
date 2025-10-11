<?php

namespace WP2\Update\REST\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Thin wrapper that exposes GitHub authentication endpoints under the new controller scheme.
 */
final class GitHubAuthController extends AbstractRestController {
	private CredentialsController $credentialsController;

	public function __construct( CredentialsController $credentialsController, ?string $namespace = null ) {
		parent::__construct( $namespace );
		$this->credentialsController = $credentialsController;
	}

	/**
	 * {@inheritdoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/github/connect-url',
			[
				'methods'             => [ WP_REST_Server::READABLE, WP_REST_Server::CREATABLE ],
				'callback'            => [ $this, 'get_connect_url' ],
				'permission_callback' => $this->permission_callback(),
			]
		);

		register_rest_route(
			$this->namespace,
			'/github/exchange-code',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'exchange_code' ],
				'permission_callback' => $this->permission_callback(),
			]
		);

		register_rest_route(
			$this->namespace,
			'/github/disconnect',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'disconnect' ],
				'permission_callback' => $this->permission_callback(),
			]
		);
	}

	public function get_connect_url( WP_REST_Request $request ): WP_REST_Response {
		return $this->credentialsController->rest_get_connect_url( $request );
	}

	public function exchange_code( WP_REST_Request $request ): WP_REST_Response {
		return $this->credentialsController->rest_exchange_code( $request );
	}

	public function disconnect( WP_REST_Request $request ): WP_REST_Response {
		return $this->credentialsController->rest_disconnect( $request );
	}
}
