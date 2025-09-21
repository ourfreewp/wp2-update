<?php
namespace WP2\Update\Admin\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP2\Update\Utils\SharedUtils;

/**
 * Registers CPTs, Meta Boxes, Columns, and related admin flows.
 */
final class Init {

	/**
	 * Registers all necessary WordPress hooks for the models.
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_cpts' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_metadata' ], 10, 2 );
		add_action( 'post_edit_form_tag', [ $this, 'add_form_enctype' ] );

		add_filter( 'manage_wp2_repository_posts_columns', [ $this, 'add_repository_custom_columns' ] );
		add_action( 'manage_wp2_repository_posts_custom_column', [ $this, 'populate_repository_custom_columns' ], 10, 2 );

		add_action( 'admin_notices', [ $this, 'show_automated_setup_success_notice' ] );
		add_action( 'admin_notices', [ $this, 'show_automated_setup_error_notice' ] );

		// Hook into the standard post save redirect to trigger our flow.
		add_filter( 'redirect_post_location', [ $this, 'trigger_github_flow_on_new_post_save' ], 10, 2 );
	}

	/**
	 * Adds enctype attribute for file uploads.
	 */
	public function add_form_enctype(): void {
		echo ' enctype="multipart/form-data"';
	}

	/**
	 * Registers CPTs.
	 */
	public function register_cpts(): void {
		register_post_type(
			'wp2_github_app',
			[
				'label'        => __( 'App Connections', 'wp2-update' ),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'menu_icon'    => 'dashicons-github',
				'supports'     => [ 'title' ],
				'rewrite'      => false,
				'map_meta_cap' => true,
			]
		);

		register_post_type(
			'wp2_repository',
			[
				'label'         => __( 'Managed Repositories', 'wp2-update' ),
				'public'        => false,
				'show_ui'       => true,
				'show_in_menu'  => false,
				'menu_icon'     => 'dashicons-book-alt',
				'supports'      => [ 'title'],
				'rewrite'       => false,
				'capabilities'  => [
					'create_posts' => 'do_not_allow',
				],
				'map_meta_cap'  => true,
			]
		);

		register_post_meta(
			'wp2_github_app',
			'custom_description',
			[
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'description'  => __( 'Custom description for the GitHub App.', 'wp2-update' ),
			]
		);

		register_post_meta(
			'wp2_repository',
			'custom_description',
			[
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'description'  => __( 'Custom description for the repository.', 'wp2-update' ),
			]
		);
	}

	/**
	 * Adds meta boxes.
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'wp2_github_app_credentials',
			__( 'GitHub App Setup', 'wp2-update' ),
			[ $this, 'render_github_app_meta_box' ],
			'wp2_github_app',
			'normal',
			'high'
		);

		add_meta_box(
			'wp2_repository_details',
			__( 'Repository Details', 'wp2-update' ),
			[ $this, 'render_repository_meta_box' ],
			'wp2_repository',
			'normal',
			'high'
		);
	}

	/**
	 * Renders the meta box for the GitHub App CPT. The content changes depending
	 * on whether it's a new post or an existing one.
	 *
	 * @param \WP_Post $post
	 */
	public function render_github_app_meta_box( \WP_Post $post ): void {
		global $pagenow;

		// --- On the "Add New" screen, show a simplified form ---
		if ( 'post-new.php' === $pagenow ) {
			wp_nonce_field( 'wp2_github_app_save_meta', 'wp2_github_app_nonce' );
			?>
			<h3><?php esc_html_e( 'App Ownership', 'wp2-update' ); ?></h3>
			<p><?php esc_html_e( 'Before creating the App Connection, specify if it will be owned by a GitHub Organization.', 'wp2-update' ); ?></p>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row">
							<label for="_wp2_github_organization"><?php esc_html_e( 'GitHub Organization (Optional)', 'wp2-update' ); ?></label>
						</th>
						<td>
							<input type="text" name="_wp2_github_organization" id="_wp2_github_organization" class="regular-text" placeholder="e.g., my-company">
							<p class="description"><?php esc_html_e( 'If this app should be owned by an organization, enter its name here. Otherwise, leave it blank to create it under your personal account.', 'wp2-update' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
			<p><em><?php esc_html_e( 'After specifying the owner, enter a title for this connection above and click "Publish". You will then be redirected here, and the GitHub App creation page will open in a new tab.', 'wp2-update' ); ?></em></p>
			<?php
			return; // Stop rendering for the "new post" screen.
		}

		// --- On the "Edit" screen, show the full configuration form ---
		if ( isset( $_GET['wp2_init_flow_nonce'] ) && wp_verify_nonce( sanitize_key( $_GET['wp2_init_flow_nonce'] ), 'wp2_init_flow_' . $post->ID ) ) {
			$github_url = get_transient( 'wp2_github_url_' . $post->ID );
			if ( $github_url ) {
				delete_transient( 'wp2_github_url_' . $post->ID );
				?>
				<script>
					document.addEventListener('DOMContentLoaded', function() {
						window.open('<?php echo esc_url_raw( $github_url ); ?>', '_blank');
					});
				</script>
				<?php
			}
		}

		wp_nonce_field( 'wp2_github_app_save_meta', 'wp2_github_app_nonce' );
		$private_key_set = (bool) get_post_meta( $post->ID, '_wp2_private_key_content', true );
		$organization    = get_post_meta( $post->ID, '_wp2_github_organization', true );
		?>
		<p class="description">
			<?php esc_html_e( 'After creating the app on GitHub, copy the generated credentials into the fields below, upload the private key, and save this connection.', 'wp2-update' ); ?>
		</p>
		<table class="form-table">
			<tbody>
				<?php if ( ! empty( $organization ) ) : ?>
					<tr>
						<th><?php esc_html_e( 'GitHub Organization', 'wp2-update' ); ?></th>
						<td>
							<code><?php echo esc_html( $organization ); ?></code>
							<p class="description"><?php esc_html_e( 'This app is configured to be owned by this organization.', 'wp2-update' ); ?></p>
						</td>
					</tr>
				<?php endif; ?>
				<tr>
					<th><label for="_wp2_app_id"><?php esc_html_e( 'App ID', 'wp2-update' ); ?></label></th>
					<td><input type="text" id="_wp2_app_id" name="_wp2_app_id" value="<?php echo esc_attr( get_post_meta( $post->ID, '_wp2_app_id', true ) ); ?>" class="widefat" /></td>
				</tr>
				<tr>
					<th><label for="_wp2_client_id"><?php esc_html_e( 'Client ID', 'wp2-update' ); ?></label></th>
					<td><input type="text" id="_wp2_client_id" name="_wp2_client_id" value="<?php echo esc_attr( get_post_meta( $post->ID, '_wp2_client_id', true ) ); ?>" class="widefat" /></td>
				</tr>
				<tr>
					<th><label for="_wp2_client_secret"><?php esc_html_e( 'Client Secret', 'wp2-update' ); ?></label></th>
					<td>
						<input type="password" id="_wp2_client_secret" name="_wp2_client_secret" value="" class="widefat" autocomplete="new-password" />
						<p class="description"><?php esc_html_e( 'Enter a new Client Secret to update. Leave blank to keep the existing one.', 'wp2-update' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="_wp2_private_key_file"><?php esc_html_e( 'Private Key (.pem)', 'wp2-update' ); ?></label></th>
					<td>
						<?php if ( $private_key_set ) : ?>
							<p style="color:#28a745;font-weight:bold;"><?php esc_html_e( '✔️ Private key is set and saved.', 'wp2-update' ); ?></p>
							<label><input type="checkbox" name="clear_wp2_private_key" value="1"> <?php esc_html_e( 'Clear the saved key to upload a new one.', 'wp2-update' ); ?></label>
						<?php else : ?>
							<input type="file" id="_wp2_private_key_file" name="_wp2_private_key_file" class="widefat" accept=".pem" />
							<p class="description"><?php esc_html_e( 'Upload the .pem file generated by GitHub.', 'wp2-update' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><label for="_wp2_webhook_secret"><?php esc_html_e( 'Webhook Secret', 'wp2-update' ); ?></label></th>
					<td><input type="text" id="_wp2_webhook_secret" name="_wp2_webhook_secret" value="<?php echo esc_attr( get_post_meta( $post->ID, '_wp2_webhook_secret', true ) ); ?>" class="widefat" /></td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Hooks into the post redirect logic to trigger the GitHub flow on the first save.
	 */
	public function trigger_github_flow_on_new_post_save( string $location, int $post_id ): string {
		// Only act on our CPT, and only on the very first save from a new post screen.
		if (
			get_post_type( $post_id ) !== 'wp2_github_app' ||
			! isset( $_POST['original_post_status'] ) ||
			'auto-draft' !== $_POST['original_post_status']
		) {
			return $location;
		}

		$organization = get_post_meta( $post_id, '_wp2_github_organization', true );

		// Generate the GitHub URL with the correct base.
		if ( ! empty( $organization ) ) {
			$base_url = 'https://github.com/organizations/' . rawurlencode( $organization ) . '/settings/apps/new';
		} else {
			$base_url = 'https://github.com/settings/apps/new';
		}

		$site_hash     = substr( md5( (string) home_url() ), 0, 8 );
		$app_name      = 'WP2 Update (' . $site_hash . ') - Post ' . $post_id;
		$post_edit_url = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
		$params        = [
			'name'                       => $app_name,
			'url'                        => home_url( '/' ),
			'webhook_active'             => 'true',
			'webhook_url'                => home_url( '/wp-json/wp2-update/v1/github/webhooks' ),
			'public'                     => 'false',
			'contents'                   => 'read',
			'callback_urls[]'            => $post_edit_url,
			'setup_url'                  => $post_edit_url,
			'setup_on_update'            => 'true',
			'request_oauth_on_install'   => 'true',
		];
		$events        = [ 'release', 'installation' ];
		$github_url    = $base_url . '?' . http_build_query( $params );
		foreach ( $events as $event ) {
			$github_url .= '&events[]=' . $event;
		}

		// Store the URL in a transient to be opened by JavaScript.
		$init_flow_nonce = wp_create_nonce( 'wp2_init_flow_' . $post_id );
		set_transient( 'wp2_github_url_' . $post_id, $github_url, MINUTE_IN_SECONDS );

		// Add our nonce to the redirect URL that WordPress is already creating.
		return add_query_arg( 'wp2_init_flow_nonce', $init_flow_nonce, $location );
	}


	/**
	 * @param \WP_Post $post
	 */
	public function render_repository_meta_box( \WP_Post $post ): void {
		$managing_app_id = get_post_meta( $post->ID, '_managing_app_post_id', true );
		$health_status   = get_post_meta( $post->ID, '_health_status', true );
		$health_message  = get_post_meta( $post->ID, '_health_message', true );
		$last_synced     = get_post_meta( $post->ID, '_last_synced_timestamp', true );
		?>
		<p><?php esc_html_e( 'This data is automatically managed by the background sync process.', 'wp2-update' ); ?></p>
		<table class="form-table">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Managing App', 'wp2-update' ); ?></th>
					<td><?php echo $managing_app_id ? esc_html( get_the_title( (int) $managing_app_id ) ) : esc_html__( 'N/A', 'wp2-update' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Health Status', 'wp2-update' ); ?></th>
					<td>
						<strong style="color: <?php echo ( 'ok' === $health_status ) ? '#28a745' : '#dc3545'; ?>;">
							<?php echo esc_html( ucfirst( (string) $health_status ) ); ?>
						</strong>
						<p><em><?php echo esc_html( (string) $health_message ); ?></em></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Last Synced', 'wp2-update' ); ?></th>
					<td><?php echo $last_synced ? esc_html( wp_date( 'Y-m-d H:i:s', (int) $last_synced ) ) : esc_html__( 'Never', 'wp2-update' ); ?></td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * @param int      $post_id
	 * @param \WP_Post $post
	 */
	public function save_metadata( int $post_id, \WP_Post $post ): void {
		if (
			'wp2_github_app' !== $post->post_type
			|| ! isset( $_POST['wp2_github_app_nonce'] )
			|| ! wp_verify_nonce( $_POST['wp2_github_app_nonce'], 'wp2_github_app_save_meta' )
			|| ! current_user_can( 'edit_post', $post_id )
		) {
			return;
		}
		// Save the organization field, only present on the initial save.
		if ( isset( $_POST['_wp2_github_organization'] ) ) {
			update_post_meta( $post_id, '_wp2_github_organization', sanitize_text_field( wp_unslash( $_POST['_wp2_github_organization'] ) ) );
		}

		update_post_meta( $post_id, '_wp2_app_id', sanitize_text_field( $_POST['_wp2_app_id'] ?? '' ) );
		update_post_meta( $post_id, '_wp2_webhook_secret', sanitize_text_field( $_POST['_wp2_webhook_secret'] ?? '' ) );
		update_post_meta( $post_id, '_wp2_client_id', sanitize_text_field( $_POST['_wp2_client_id'] ?? '' ) );

		if ( isset( $_POST['_wp2_client_secret'] ) && '' !== (string) $_POST['_wp2_client_secret'] ) {
			update_post_meta( $post_id, '_wp2_client_secret', SharedUtils::encrypt( sanitize_text_field( (string) $_POST['_wp2_client_secret'] ) ) );
		}

		if ( isset( $_POST['clear_wp2_private_key'] ) && '1' === $_POST['clear_wp2_private_key'] ) {
			delete_post_meta( $post_id, '_wp2_private_key_content' );
		} elseif ( isset( $_FILES['_wp2_private_key_file'] ) && UPLOAD_ERR_OK === (int) $_FILES['_wp2_private_key_file']['error'] ) {
			$uploaded_file = $_FILES['_wp2_private_key_file'];
			if ( 'pem' === strtolower( (string) pathinfo( $uploaded_file['name'], PATHINFO_EXTENSION ) ) ) {
				$key_content = file_get_contents( $uploaded_file['tmp_name'] );
				if ( false !== $key_content && '' !== $key_content ) {
					update_post_meta( $post_id, '_wp2_private_key_content', SharedUtils::encrypt( $key_content ) );
				}
			}
		}

		$this->trigger_post_save_actions( $post_id );
	}

	/**
	 * @param int $post_id
	 */
	private function trigger_post_save_actions( int $post_id ): void {
		delete_transient( 'wp2_repo_app_map' );
		if ( class_exists( '\\WP2\\Update\\Core\\Updates\\PackageFinder' ) ) {
			( new \WP2\Update\Core\Updates\PackageFinder() )->clear_cache();
		}
		if ( class_exists( '\\WP2\\Update\\Core\\Tasks\\Scheduler' ) ) {
			\WP2\Update\Core\Tasks\Scheduler::schedule_health_check_for_app( $post_id );
			\WP2\Update\Core\Tasks\Scheduler::schedule_sync_for_app( $post_id );
		}
	}

	/**
	 * @param array<string,string> $columns
	 * @return array<string,string>
	 */
	public function add_repository_custom_columns( array $columns ): array {
		$new_columns = [];
		foreach ( $columns as $key => $title ) {
			$new_columns[ $key ] = $title;
			if ( 'title' === $key ) {
				$new_columns['managing_app'] = __( 'Managing App', 'wp2-update' );
				$new_columns['health_status'] = __( 'Health Status', 'wp2-update' );
				$new_columns['last_synced'] = __( 'Last Synced', 'wp2-update' );
			}
		}
		return $new_columns;
	}

	/**
	 * @param string $column
	 * @param int    $post_id
	 */
	public function populate_repository_custom_columns( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'managing_app':
				$app_post_id = (int) get_post_meta( $post_id, '_managing_app_post_id', true );
				echo $app_post_id ? esc_html( get_the_title( $app_post_id ) ) : esc_html__( 'N/A', 'wp2-update' );
				break;

			case 'health_status':
				$status  = (string) get_post_meta( $post_id, '_health_status', true );
				$message = (string) get_post_meta( $post_id, '_health_message', true );
				echo ( 'ok' === $status )
					? '<span class="dashicons dashicons-yes-alt" style="color:#46b450;" title="' . esc_attr( $message ) . '"></span>'
					: '<span class="dashicons dashicons-warning" style="color:#d63638;" title="' . esc_attr( $message ) . '"></span>';
				break;

			case 'last_synced':
				$timestamp = (int) get_post_meta( $post_id, '_last_synced_timestamp', true );
				echo $timestamp ? esc_html( wp_date( 'Y-m-d H:i:s', $timestamp ) ) : esc_html__( 'Never', 'wp2-update' );
				break;
		}
	}

	/**
	 * Success notice after automated setup.
	 */
	public function show_automated_setup_success_notice(): void {
		if ( isset( $_GET['message'] ) && 'app_created' === $_GET['message'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' .
				esc_html__( 'Success! Your GitHub App has been created. Please copy the credentials into the form below.', 'wp2-update' ) .
				'</p></div>';
		}
	}

    /**
     * Error notice after automated setup failure.
     */
    public function show_automated_setup_error_notice(): void {
        $error_code = get_transient( 'wp2_github_setup_error' );
        if ( false !== $error_code ) {
            delete_transient( 'wp2_github_setup_error' );
            $message = __( 'An unknown error occurred during the automated setup.', 'wp2-update' );
            if ( 'invalid_request' === $error_code ) {
                $message = __( 'Invalid callback request or session expired. Please try again.', 'wp2-update' );
            }
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
        }
    }

	/**
	 * @param array<string,mixed> $payload
	 */
	public function handle_github_installation_event( array $payload ): void {
		if ( isset( $payload['installation']['id'], $payload['installation']['app_id'] ) ) {
			$installation_id = $payload['installation']['id'];
			$app_id          = $payload['installation']['app_id'];

			$app_query = new \WP_Query(
				[
					'post_type'      => 'wp2_github_app',
					'meta_key'       => '_wp2_app_id',
					'meta_value'     => $app_id,
					'posts_per_page' => 1,
					'fields'         => 'ids',
				]
			);

			if ( ! empty( $app_query->posts ) ) {
				$app_post_id = $app_query->posts[0];
				update_post_meta( $app_post_id, '_wp2_installation_id', $installation_id );
			}
		}
	}
}

