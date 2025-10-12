<?php

namespace WP2\Update\REST\Controllers;

use WP2\Update\Core\API\ConnectionService;
use WP2\Update\Core\API\CredentialService;
use WP2\Update\Utils\Logger;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function __;
use function sanitize_text_field;

/**
 * REST controller responsible for CRUD operations on GitHub Apps.
 */
final class AppsController extends AbstractRestController {
	private CredentialService $credentialService;
	private ConnectionService $connectionService;

	public function __construct(
		CredentialService $credentialService,
		ConnectionService $connectionService,
		?string $namespace = null
	) {
		parent::__construct( $namespace );
		$this->credentialService = $credentialService;
		$this->connectionService = $connectionService;
	}

	/**
	 * {@inheritdoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/apps',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_apps' ],
				'permission_callback' => $this->permission_callback(),
			]
		);

		register_rest_route(
			$this->namespace,
			'/apps',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_app' ],
				'permission_callback' => $this->permission_callback(),
				'args'                => [
					'name' => [
						'required'    => true,
						'type'        => 'string',
						'description' => __( 'The display name for the GitHub App.', 'wp2-update' ),
					],
					'manifest' => [
						'required'    => false,
						'type'        => 'string',
						'description' => __( 'Optional manifest JSON to seed the app wizard.', 'wp2-update' ),
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/apps/(?P<app_uid>[a-zA-Z0-9_-]+)',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_app' ],
				'permission_callback' => $this->permission_callback(),
				'args'                => [
					'name' => [
						'required'    => false,
						'type'        => 'string',
						'description' => __( 'Optional new display name.', 'wp2-update' ),
					],
					'status' => [
						'required'    => false,
						'type'        => 'string',
						'description' => __( 'Optional status for the app.', 'wp2-update' ),
					],
					'organization' => [
						'required'    => false,
						'type'        => 'string',
						'description' => __( 'Optional organization slug.', 'wp2-update' ),
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/apps/(?P<app_uid>[a-zA-Z0-9_-]+)',
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_app' ],
				'permission_callback' => $this->permission_callback(),
			]
		);
	}

	/**
	 * Fetch configured apps.
	 */
	public function list_apps( WP_REST_Request $request ): WP_REST_Response {
		try {
			$apps = $this->credentialService->get_all_apps();

			return new WP_REST_Response(
				[
					'apps' => $apps,
				],
				200
			);
		} catch ( \Throwable $exception ) {
			Logger::log( 'ERROR', 'Failed to list apps: ' . $exception->getMessage() );

			return new WP_REST_Response(
				[
					'error'   => true,
					'message' => esc_html__( 'Unable to retrieve apps.', 'wp2-update' ),
				],
				500
			);
		}
	}

	/**
	 * Create a new app record.
	 */
	public function create_app( WP_REST_Request $request ): WP_REST_Response {
		try {
			$name = sanitize_text_field( (string) $request->get_param( 'name' ) );
			if ( '' === $name ) {
				return $this->respond(
					[
						'error' => __( 'App name is required.', 'wp2-update' ),
					],
					400
				);
			}

			$manifest = $request->get_param( 'manifest' );
			if ( ! empty( $manifest ) ) {
				// Sanitize and validate the manifest
				$manifest = wp_kses_post( $manifest );
				$decoded_manifest = json_decode( $manifest, true );
				if ( json_last_error() !== JSON_ERROR_NONE ) {
					return $this->respond(
						[
							'error' => __( 'Invalid manifest JSON.', 'wp2-update' ),
						],
						400
					);
				}
			}

			$app = $this->connectionService->save_app(
				[
					'name'       => $name,
					'manifest'   => $manifest,
					'created_at' => current_time( 'mysql' ),
				]
			);

			return $this->respond(
				[
					'message' => __( 'App created successfully.', 'wp2-update' ),
					'app'     => $app,
				],
				201
			);
		} catch ( \Throwable $error ) {
			Logger::log( 'CRITICAL', 'Failed to create app: ' . $error->getMessage() );

			return $this->respond(
				[
					'error' => __( 'Unable to create the app at this time.', 'wp2-update' ),
				],
				500
			);
		}
	}

	/**
	 * Update an existing app.
	 */
	public function update_app( WP_REST_Request $request ): WP_REST_Response {
		$appUid = (string) $request->get_param( 'app_uid' );
		if ( '' === $appUid ) {
			return $this->respond(
				[
					'error' => __( 'An app identifier is required.', 'wp2-update' ),
				],
				400
			);
		}

		try {
			$payload = array_filter(
				[
					'name'         => $request->get_param( 'name' ),
					'status'       => $request->get_param( 'status' ),
					'organization' => $request->get_param( 'organization' ),
				],
				static fn( $value ) => null !== $value && $value !== ''
			);

			if ( empty( $payload ) ) {
				return $this->respond(
					[
						'error' => __( 'No changes supplied.', 'wp2-update' ),
					],
					400
				);
			}

			$app = $this->connectionService->update_app( $appUid, $payload );

			return $this->respond(
				[
					'message' => __( 'App updated successfully.', 'wp2-update' ),
					'app'     => $app,
				]
			);
		} catch ( \Throwable $error ) {
			Logger::log( 'CRITICAL', sprintf( 'Failed to update app %s: %s', $appUid, $error->getMessage() ) );

			return $this->respond(
				[
					'error' => __( 'Unable to update the app.', 'wp2-update' ),
				],
				500
			);
		}
	}

	/**
	 * Delete an app by its UID.
	 */
	public function delete_app( WP_REST_Request $request ): WP_REST_Response {
		$appUid = (string) $request->get_param( 'app_uid' );

		if ( '' === $appUid ) {
			return $this->respond(
				[
					'error' => __( 'An app identifier is required.', 'wp2-update' ),
				],
				400
			);
		}

		try {
			$this->connectionService->delete_app( $appUid );

			return $this->respond(
				[
					'message' => __( 'App deleted successfully.', 'wp2-update' ),
				]
			);
		} catch ( \Throwable $error ) {
			Logger::log( 'CRITICAL', sprintf( 'Failed to delete app %s: %s', $appUid, $error->getMessage() ) );

			return $this->respond(
				[
					'error' => __( 'Unable to delete the app.', 'wp2-update' ),
				],
				500
			);
		}
	}
}

