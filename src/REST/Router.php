<?php

namespace WP2\Update\REST;

use WP_REST_Request;
use WP2\Update\REST\Controllers\CredentialsController;
use WP2\Update\REST\Controllers\PackagesController;
use WP2\Update\REST\Controllers\RestControllerInterface;
use WP2\Update\Security\Permissions;
use function __;
use function current_user_can;

/**
 * Coordinates registration of REST routes across modular controllers.
 */
final class Router {
	private CredentialsController $credentialsController;
	private PackagesController $packagesController;

	/**
	 * @var RestControllerInterface[]
	 */
	private array $modularControllers;

	/**
	 * @param RestControllerInterface[] $modularControllers
	 */
	public function __construct(
		CredentialsController $credentialsController,
		PackagesController $packagesController,
		array $modularControllers = []
	) {
		$this->credentialsController = $credentialsController;
		$this->packagesController    = $packagesController;
		$this->modularControllers    = $modularControllers;
	}

	/**
	 * Register all REST routes for the plugin.
	 */
	public function register_routes(): void {
		foreach ( $this->modularControllers as $controller ) {
			if ( $controller instanceof RestControllerInterface ) {
				$controller->register_routes();
			}
		}

		// TODO: migrate remaining endpoints into dedicated controllers.
		$this->register_credentials_routes();
		$this->register_package_routes();
	}

	private function register_credentials_routes(): void {
		register_rest_route(
			'wp2-update/v1',
			'/save-credentials',
			[
				'methods'             => 'POST',
				'callback'            => [ $this->credentialsController, 'rest_save_credentials' ],
				'permission_callback' => [ Permissions::class, 'current_user_can_manage' ],
			]
		);
	}

	private function register_package_routes(): void {
		register_rest_route(
			'wp2-update/v1',
			'/run-update-check',
			[
				'methods'             => 'POST',
				'callback'            => [ $this->packagesController, 'rest_run_update_check' ],
				'permission_callback' => [ Permissions::class, 'current_user_can_manage' ],
			]
		);

		register_rest_route(
			'wp2-update/v1',
			'/sync-packages',
			[
				'methods'             => 'GET',
				'callback'            => [ $this->packagesController, 'sync_packages' ],
				'permission_callback' => [ Permissions::class, 'current_user_can_manage' ],
			]
		);

		register_rest_route(
			'wp2-update/v1',
			'/manage-packages',
			[
				'methods'             => 'POST',
				'callback'            => [ $this->packagesController, 'manage_packages' ],
				'permission_callback' => [ Permissions::class, 'current_user_can_manage' ],
			]
		);

		register_rest_route(
			'wp2-update/v1',
			'/package/(?P<repo_slug>[\w-]+/[\w-]+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this->packagesController, 'rest_get_package_status' ],
				'permission_callback' => static function ( WP_REST_Request $request ): bool {
					return current_user_can( 'manage_options' )
						&& PackagesController::check_permissions( $request );
				},
			]
		);

		register_rest_route(
			'wp2-update/v1',
			'/packages/assign',
			[
				'methods'             => 'POST',
				'callback'            => [ $this->packagesController, 'assign_package' ],
				'permission_callback' => static function ( WP_REST_Request $request ): bool {
					return current_user_can( 'manage_options' )
						&& PackagesController::check_permissions( $request );
				},
				'args'                => [
					'app_id' => [
						'required'    => true,
						'type'        => 'string',
						'description' => __( 'The app UID that should manage the package.', 'wp2-update' ),
					],
					'repo_id' => [
						'required'    => true,
						'type'        => 'string',
						'description' => __( 'The repository identifier to assign.', 'wp2-update' ),
					],
				],
			]
		);
	}
}
