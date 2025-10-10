<?php

namespace WP2\Update\REST\Controllers;

use WP2\Update\Core\API\ConnectionService;
use WP2\Update\Security\Permissions;
use WP2\Update\Utils\Logger;
use WP_REST_Request;
use WP_REST_Response;

final class ConnectionController {
	private ConnectionService $connectionService;

	public function __construct( ConnectionService $connectionService ) {
		$this->connectionService = $connectionService;
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
			$status      = $this->connectionService->test_connection();
			$http_status = $status['success'] ? 200 : 400;

			return $this->format_response( [
				'connected' => (bool) ( $status['success'] ?? false ),
				'message'   => (string) ( $status['message'] ?? '' ),
			], $http_status );

		} catch (\Throwable $e) { // FIX: Catch any \Throwable, including \Error and all \Exceptions
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
}