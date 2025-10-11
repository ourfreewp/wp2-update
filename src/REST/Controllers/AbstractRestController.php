<?php

namespace WP2\Update\REST\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP2\Update\Security\Permissions;

/**
 * Base controller that provides common helpers for REST endpoints.
 */
abstract class AbstractRestController implements RestControllerInterface {
	/**
	 * REST API namespace used by the plugin.
	 */
	protected string $namespace;

	public function __construct(?string $namespace = null) {
		$this->namespace = $namespace ?? 'wp2-update/v1';
	}

	/**
	 * Return the namespace the controller should operate within.
	 */
	public function get_namespace(): string {
		return $this->namespace;
	}

	/**
	 * Create a standardized REST response.
	 *
	 * @param mixed $data    Response payload.
	 * @param int   $status  HTTP status code.
	 */
	protected function respond($data, int $status = 200): WP_REST_Response {
		if ($data instanceof WP_REST_Response) {
			return $data;
		}

		return new WP_REST_Response(
			[
				'success' => $status >= 200 && $status < 300,
				'data'    => $data,
			],
			$status
		);
	}

	/**
	 * Provide a permission callback for WordPress core.
	 */
	protected function permission_callback(): callable {
		return static function ( WP_REST_Request $request ): bool {
			return Permissions::current_user_can_manage( $request );
		};
	}
}

