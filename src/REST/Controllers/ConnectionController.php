<?php

namespace WP2\Update\REST\Controllers;

use WP2\Update\Core\API\ConnectionService;
use WP2\Update\Core\API\CredentialService;
use WP2\Update\Core\Updates\PackageFinder;
use WP2\Update\Security\Permissions;
use WP2\Update\Utils\Logger;
use WP_REST_Request;
use WP_REST_Response;

final class ConnectionController {
	private ConnectionService $connectionService;
	private CredentialService $credentialService;
	private ?PackageFinder $packageFinder;

	public function __construct( ConnectionService $connectionService, CredentialService $credentialService, ?PackageFinder $packageFinder = null ) {
		$this->connectionService = $connectionService;
		$this->credentialService = $credentialService;
		$this->packageFinder     = $packageFinder;
	}

	public static function check_permissions( WP_REST_Request $request ): bool {
		return Permissions::current_user_can_manage( $request );
	}

	private function format_response( array $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response( [
			'success' => $status >= 200 && $status < 300,
			'data'    => $data,
		], $status );
	}

	public function get_connection_status( WP_REST_Request $request ): WP_REST_Response {
		try {
			$status = $this->connectionService->get_connection_status();

			if ( in_array( $status['status'], [ 'not_configured', 'not_configured_with_packages' ], true ) && $this->packageFinder ) {
				$packages = $this->packageFinder->get_managed_packages();
				if ( ! empty( $packages ) ) {
					$status['status']             = 'not_configured_with_packages';
					$status['unlinked_packages'] = array_values( $packages );
				}
			}

			$http_status = 200;
			if ( 'connection_error' === $status['status'] ) {
				$http_status = 400;
			}

			return $this->format_response( $status, $http_status );

		} catch ( \Throwable $e ) { // FIX: Catch any \Throwable, including \Error and all \Exceptions
			// Log the specific error for the site admin to debug
			Logger::log( 'CRITICAL', 'A fatal error occurred during connection status check: ' . $e->getMessage() );

			// Return a clear, user-friendly error response instead of a 500 crash
			return new WP_REST_Response( [
				'success' => false,
				'data'    => [
					'connected' => false,
					'message'   => 'Could not check connection status due to a server error. This may be caused by corrupted credentials. Please try the "Disconnect" button if it appears, or try reconnecting.',
				]
			], 503 ); // 503 Service Unavailable is an appropriate status code
		}
	}

	public function rest_validate_connection( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->connectionService->validate_connection();

		$http_status = $result['success'] ? 200 : 400;

		// Log the validation result for debugging purposes
		if ( ! $result['success'] ) {
			\WP2\Update\Utils\Logger::log( 'ERROR', 'Connection validation failed: ' . ( $result['message'] ?? 'Unknown error' ) );
		} else {
			\WP2\Update\Utils\Logger::log( 'INFO', 'Connection validation succeeded.' );
		}

		return $this->format_response( [
			'message' => (string) ( $result['message'] ?? '' ),
			'details' => $result['details'] ?? [],
		], $http_status );
	}

	public function get_health_status( WP_REST_Request $request ): WP_REST_Response {
		return $this->format_response( [
			'status'    => 'healthy',
			'timestamp' => time(),
		], 200 );
	}

	/**
	 * Reports the installation status of the GitHub App.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response The response containing installation status.
	 */
	public function get_installation_status( WP_REST_Request $request ): WP_REST_Response {
		$installationId = $this->credentialService->get_installation_id();

		if ( ! $installationId ) {
			return new WP_REST_Response( [
				'installed' => false,
				'message' => __( 'The GitHub App is not installed.', 'wp2-update' ),
			], 200 );
		}

		return new WP_REST_Response( [
			'installed' => true,
			'installation_id' => $installationId,
			'message' => __( 'The GitHub App is installed.', 'wp2-update' ),
		], 200 );
	}
}
