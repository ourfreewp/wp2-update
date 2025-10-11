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
            $appId  = $request->get_param('app_id');
            $status = $this->connectionService->get_connection_status($appId ? (string) $appId : null);

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

		} catch ( \Throwable $e ) {
			// Log the specific error for the site admin to debug
			Logger::log( 'CRITICAL', 'A fatal error occurred during connection status check: ' . $e->getMessage() );

			// Return a clear, user-friendly error response instead of a 500 crash
			return $this->format_response([
				'connected' => false,
				'message'   => 'Could not check connection status due to a server error. This may be caused by corrupted credentials. Please try the "Disconnect" button if it appears, or try reconnecting.',
			], 503); // 503 Service Unavailable is an appropriate status code
		}
	}

    public function rest_validate_connection( WP_REST_Request $request ): WP_REST_Response {
        $appId  = $request->get_param('app_id');
        $result = $this->connectionService->validate_connection($appId ? (string) $appId : null);

		$http_status = $result['success'] ? 200 : 400;

		// Log the validation result for debugging purposes
		if ( ! $result['success'] ) {
			Logger::log( 'ERROR', 'Connection validation failed: ' . ( $result['message'] ?? 'Unknown error' ) );
		} else {
			Logger::log( 'INFO', 'Connection validation succeeded.' );
		}

		return $this->format_response([
			'message' => (string) ( $result['message'] ?? '' ),
			'details' => $result['details'] ?? [],
		], $http_status);
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
        $appId = $request->get_param('app_id');
        $installationId = $this->credentialService->get_installation_id($appId ? (string) $appId : null);

        if ( ! $installationId ) {
            return $this->format_response([
                'installed' => false,
                'message' => __( 'The GitHub App is not installed.', 'wp2-update' ),
            ], 200);
        }

        return $this->format_response([
            'installed' => true,
            'installation_id' => $installationId,
            'message' => __( 'The GitHub App is installed.', 'wp2-update' ),
        ], 200);
    }

    /**
     * List all configured GitHub apps (sanitised).
     */
    public function list_apps( WP_REST_Request $request ): WP_REST_Response {
        $apps = $this->credentialService->get_app_summaries();

        return $this->format_response([
            'apps' => $apps,
        ]);
    }

    /**
     * Create a new app via manifest or manual input.
     */
    public function create_app( WP_REST_Request $request ): WP_REST_Response {
        try {
            $name = $request->get_param('name');
            $manifest = $request->get_param('manifest');

            if ( empty( $name ) ) {
                return $this->format_response([
                    'error' => 'App name is required.',
                ], 400);
            }

            $appData = [
                'name' => $name,
                'manifest' => $manifest,
                'created_at' => current_time('mysql'),
            ];

            // Save the app using the ConnectionService
            $app = $this->connectionService->save_app( $appData );

            return $this->format_response([
                'message' => 'App created successfully.',
                'app' => $app,
            ], 201);
        } catch ( \Throwable $e ) {
            Logger::log( 'CRITICAL', 'Error creating app: ' . $e->getMessage() );

            return $this->format_response([
                'error' => 'An error occurred while creating the app.',
            ], 500);
        }
    }

    /**
     * Update an existing app.
     */
    public function update_app( WP_REST_Request $request ): WP_REST_Response {
        try {
            $id = $request->get_param('id');
            $name = $request->get_param('name');
            $status = $request->get_param('status');
            $organization = $request->get_param('organization');

            if ( empty( $id ) ) {
                return $this->format_response([
                    'error' => 'App ID is required.',
                ], 400);
            }

            $updatedData = array_filter([
                'name' => $name,
                'status' => $status,
                'organization' => $organization,
            ]);

            // Update the app using the ConnectionService
            $app = $this->connectionService->update_app( $id, $updatedData );

            return $this->format_response([
                'message' => 'App updated successfully.',
                'app' => $app,
            ], 200);
        } catch ( \Throwable $e ) {
            Logger::log( 'CRITICAL', 'Error updating app: ' . $e->getMessage() );

            return $this->format_response([
                'error' => 'An error occurred while updating the app.',
            ], 500);
        }
    }

    /**
     * Delete an existing app.
     */
    public function delete_app( WP_REST_Request $request ): WP_REST_Response {
        try {
            $id = $request->get_param('id');

            if ( empty( $id ) ) {
                return $this->format_response([
                    'error' => 'App ID is required.',
                ], 400);
            }

            // Delete the app using the ConnectionService
            $this->connectionService->delete_app( $id );

            return $this->format_response([
                'message' => 'App deleted successfully.',
            ], 200);
        } catch ( \Throwable $e ) {
            Logger::log( 'CRITICAL', 'Error deleting app: ' . $e->getMessage() );

            return $this->format_response([
                'error' => 'An error occurred while deleting the app.',
            ], 500);
        }
    }

    /**
     * Assign a repository to an app.
     */
    public function assign_package( WP_REST_Request $request ): WP_REST_Response {
        try {
            $appId = $request->get_param('app_id');
            $repoId = $request->get_param('repo_id');

            if ( empty( $appId ) || empty( $repoId ) ) {
                return $this->format_response([
                    'error' => 'Both app_id and repo_id are required.',
                ], 400);
            }

            // Assign the package using the ConnectionService
            $this->connectionService->assign_package( $appId, $repoId );

            return $this->format_response([
                'message' => 'Package assigned successfully.',
            ], 200);
        } catch ( \Throwable $e ) {
            Logger::log( 'CRITICAL', 'Error assigning package: ' . $e->getMessage() );

            return $this->format_response([
                'error' => 'An error occurred while assigning the package.',
            ], 500);
        }
    }
}
