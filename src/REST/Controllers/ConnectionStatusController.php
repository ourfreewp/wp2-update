<?php

namespace WP2\Update\REST\Controllers;

use WP2\Update\Core\API\ConnectionService;
use WP2\Update\Core\API\CredentialService;
use WP2\Update\Core\Updates\PackageFinder;
use WP2\Update\Utils\Logger;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function __;

/**
 * Handles read-only endpoints that report connection and installation status.
 */
final class ConnectionStatusController extends AbstractRestController {
	private ConnectionService $connectionService;
	private CredentialService $credentialService;
	private PackageFinder $packageFinder;

	public function __construct(
		ConnectionService $connectionService,
		CredentialService $credentialService,
		PackageFinder $packageFinder,
		?string $namespace = null
	) {
		parent::__construct( $namespace );
		$this->connectionService = $connectionService;
		$this->credentialService = $credentialService;
		$this->packageFinder     = $packageFinder;
	}

	/**
	 * {@inheritdoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/connection-status',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_connection_status' ],
				'permission_callback' => $this->permission_callback(),
			]
		);

		register_rest_route(
			$this->namespace,
			'/health-status',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_health_status' ],
				'permission_callback' => $this->permission_callback(),
			]
		);

		register_rest_route(
			$this->namespace,
			'/apps/(?P<app_uid>[a-zA-Z0-9_-]+)/installation',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_installation_status' ],
				'permission_callback' => $this->permission_callback(),
			]
		);
	}

	public function get_connection_status( WP_REST_Request $request ): WP_REST_Response {
		$appUid = $request->get_param( 'app_uid' );

		try {
			$status = $this->connectionService->get_connection_status(
				$appUid ? (string) $appUid : null
			);

			// Removed dependency on PackageFinder for fetching packages
			if ( in_array( $status['status'] ?? '', [ 'not_configured', 'not_configured_with_packages' ], true ) ) {
				$status['packages'] = [];
			}

			return new WP_REST_Response( $status, 200 );
		} catch ( \Throwable $exception ) {
			Logger::log( 'ERROR', 'Failed to retrieve connection status: ' . $exception->getMessage() );

			return new WP_REST_Response(
				[
					'error'   => true,
					'message' => esc_html__( 'Unable to retrieve connection status.', 'wp2-update' ),
				],
				500
			);
		}
	}

	public function get_health_status( WP_REST_Request $request ): WP_REST_Response {
		return $this->respond(
			[
				'status'    => 'healthy',
				'timestamp' => time(),
			]
		);
	}

	public function get_installation_status( WP_REST_Request $request ): WP_REST_Response {
		$appUid = (string) $request->get_param( 'app_uid' );

		if ( '' === $appUid ) {
			return $this->respond(
				[
					'installed' => false,
					'message'   => __( 'Missing app identifier.', 'wp2-update' ),
				],
				400
			);
		}

		$installationId = $this->credentialService->get_installation_id( $appUid );

		if ( ! $installationId ) {
			return $this->respond(
				[
					'installed' => false,
					'message'   => __( 'The GitHub App is not installed.', 'wp2-update' ),
				]
			);
		}

		return $this->respond(
			[
				'installed'       => true,
				'installation_id' => $installationId,
				'message'         => __( 'The GitHub App is installed.', 'wp2-update' ),
			]
		);
	}
}
