<?php
namespace WP2\Update\Admin\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP2\Update\Utils\SharedUtils;
use WP2\Update\Utils\Logger;

// Ensure all user-facing strings have the correct text domain.
const WP2_UPDATE_TEXT_DOMAIN = 'wp2-update';

/**
 * Registers CPTs, Meta Boxes, Columns, and related admin flows.
 */
final class Init {

	// Define constants for post meta keys.
    private const META_PRIVATE_KEY_CONTENT = '_wp2_private_key_content';
    private const META_GITHUB_ORGANIZATION = '_wp2_github_organization';
    private const META_APP_ID = '_wp2_app_id';
    private const META_CLIENT_ID = '_wp2_client_id';
    private const META_WEBHOOK_SECRET = '_wp2_webhook_secret';
    private const META_INSTALLATION_ID = '_wp2_installation_id';
    private const META_MANAGING_APP_ID = '_managing_app_post_id';
    private const META_HEALTH_STATUS = '_health_status';
    private const META_HEALTH_MESSAGE = '_health_message';
    private const META_LAST_SYNCED_TIMESTAMP = '_last_synced_timestamp';

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

		// Add custom columns for GitHub App post type.
		add_filter( 'manage_wp2_github_app_posts_columns', [ $this, 'add_github_app_custom_columns' ] );
		add_action( 'manage_wp2_github_app_posts_custom_column', [ $this, 'populate_github_app_custom_columns' ], 10, 2 );

		add_action( 'admin_notices', [ $this, 'show_automated_setup_success_notice' ] );
		add_action( 'admin_notices', [ $this, 'show_automated_setup_error_notice' ] );
		add_action( 'admin_notices', [ $this, 'show_installation_saved_notice' ] );

		// Hook into the standard post save redirect to trigger our flow.
		add_filter( 'redirect_post_location', [ $this, 'trigger_github_flow_on_new_post_save' ], 10, 2 );


		// Add the App Health metabox.
		add_action( 'add_meta_boxes', [ $this, 'add_app_health_metabox' ] );

		add_action('admin_post_wp2_update_check', [ $this, 'handle_update_check' ]);
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
				'label'        => __( 'App Connections', WP2_UPDATE_TEXT_DOMAIN ),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'menu_icon'    => 'dashicons-github',
				'supports'     => [ 'title', 'custom-fields' ],
				'rewrite'      => false,
				'map_meta_cap' => true,
			]
		);

		register_post_type(
			'wp2_repository',
			[
				'label'         => __( 'Managed Repositories', WP2_UPDATE_TEXT_DOMAIN ),
				'public'        => false,
				'show_ui'       => true,
				'show_in_menu'  => false,
				'menu_icon'     => 'dashicons-book-alt',
				'supports'      => [ 'title' ],
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
				'description'  => __( 'Custom description for the GitHub App.', WP2_UPDATE_TEXT_DOMAIN ),
			]
		);

		register_post_meta(
			'wp2_repository',
			'custom_description',
			[
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'description'  => __( 'Custom description for the repository.', WP2_UPDATE_TEXT_DOMAIN ),
			]
		);
	}

	/**
	 * Adds meta boxes.
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'wp2_github_app_credentials',
			__( 'GitHub App Setup', WP2_UPDATE_TEXT_DOMAIN ),
			[ $this, 'render_github_app_meta_box' ],
			'wp2_github_app',
			'normal',
			'high'
		);

		add_meta_box(
			'wp2_repository_details',
			__( 'Repository Details', WP2_UPDATE_TEXT_DOMAIN ),
			[ $this, 'render_repository_meta_box' ],
			'wp2_repository',
			'normal',
			'high'
		);

		// Add the App Logs metabox.
		add_meta_box(
			'wp2_app_logs',
			__( 'App Logs', WP2_UPDATE_TEXT_DOMAIN ),
			[ $this, 'render_app_logs_metabox' ],
			'wp2_github_app',
			'normal',
			'default'
		);

		// debug panel
		add_meta_box(
			'wp2_debug_panel',
			__( 'Debug Panel', WP2_UPDATE_TEXT_DOMAIN ),
			[ $this, 'render_debug_panel_metabox' ],
			'wp2_github_app',
			'normal',
			'default'
		);
	}

	/**
	 * Renders the meta box for the Debug Panel.
	 */
	public function render_debug_panel_metabox(): void {
		?>
		<div id="wp2-debug-panel">
			<h3><?php esc_html_e( 'Debug Information', WP2_UPDATE_TEXT_DOMAIN ); ?></h3>
			<p><?php esc_html_e( 'Here you can find debug information related to the GitHub App.', WP2_UPDATE_TEXT_DOMAIN ); ?></p>
		</div>
		<?php
		// structured dump of all post meta for this post
		global $post;
		$post_meta = get_post_meta( $post->ID );
		echo '<h4>' . esc_html__( 'Post Meta:', WP2_UPDATE_TEXT_DOMAIN ) . '</h4>';
		if ( ! empty( $post_meta ) ) {
			echo '<ul style="list-style:disc; margin-left:20px;">';
			foreach ( $post_meta as $key => $values ) {
				echo '<li><strong>' . esc_html( $key ) . ':</strong> ';
				// Obfuscate sensitive fields
				if ( in_array( $key, [ '_wp2_webhook_secret', '_wp2_client_secret', '_wp2_private_key_content' ], true ) ) {
					if ( is_array( $values ) ) {
						echo '<em>' . esc_html__( 'Obfuscated', WP2_UPDATE_TEXT_DOMAIN ) . '</em>';
					} else {
						echo '<em>' . esc_html__( 'Obfuscated', WP2_UPDATE_TEXT_DOMAIN ) . '</em>';
					}
				} elseif ( is_array( $values ) && count( $values ) === 1 ) {
					echo esc_html( $values[0] );
				} elseif ( is_array( $values ) ) {
					echo '<ul style="list-style:circle; margin-left:20px;">';
					foreach ( $values as $v ) {
						echo '<li>' . esc_html( $v ) . '</li>';
					}
					echo '</ul>';
				} else {
					echo esc_html( $values );
				}
				echo '</li>';
			}
			echo '</ul>';
		} else {
			echo '<p>' . esc_html__( 'No post meta found.', WP2_UPDATE_TEXT_DOMAIN ) . '</p>';
		}
	}

	/**
	 * Registers the App Health metabox.
	 */
	public function add_app_health_metabox(): void {
		add_meta_box(
			'wp2_app_health',
			__( 'App Health', WP2_UPDATE_TEXT_DOMAIN ),
			[ $this, 'render_app_health_metabox' ],
			'wp2_github_app',
			'side',
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
			<h3><?php esc_html_e( 'App Ownership', WP2_UPDATE_TEXT_DOMAIN ); ?></h3>
			<p><?php esc_html_e( 'Before creating the App Connection, specify if it will be owned by a GitHub Organization.', WP2_UPDATE_TEXT_DOMAIN ); ?></p>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row">
							<label for="_wp2_github_organization"><?php esc_html_e( 'GitHub Organization (Optional)', WP2_UPDATE_TEXT_DOMAIN ); ?></label>
						</th>
						<td>
							<input type="text" name="_wp2_github_organization" id="_wp2_github_organization" class="regular-text" placeholder="e.g., my-company">
							<p class="description"><?php esc_html_e( 'If this app should be owned by an organization, enter its name here. Otherwise, leave it blank to create it under your personal account.', WP2_UPDATE_TEXT_DOMAIN ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
			<p><em><?php esc_html_e( 'After specifying the owner, enter a title for this connection above and click "Publish". You will then be redirected here, and the GitHub App creation page will open in a new tab.', WP2_UPDATE_TEXT_DOMAIN ); ?></em></p>
			<?php
			return; // Stop rendering for the "new post" screen.
		}

		// --- On the "Edit" screen, show the full configuration form ---
		if ( isset( $_GET['wp2_init_flow_nonce'] ) && wp_verify_nonce( sanitize_key( $_GET['wp2_init_flow_nonce'] ), 'wp2_init_flow_' . $post->ID ) ) {
			// Ensure the nonce is sanitized before verification.
			$github_url = esc_url_raw( get_transient( 'wp2_github_url_' . $post->ID ) );
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
		$private_key_set = (bool) get_post_meta( $post->ID, self::META_PRIVATE_KEY_CONTENT, true );
		$organization    = get_post_meta( $post->ID, self::META_GITHUB_ORGANIZATION, true );
		$app_id_is_set   = (bool) get_post_meta( $post->ID, self::META_APP_ID, true );
		$client_id       = get_post_meta( $post->ID, self::META_CLIENT_ID, true );
		$webhook_secret  = get_post_meta( $post->ID, self::META_WEBHOOK_SECRET, true );
		$installation_id = get_post_meta( $post->ID, self::META_INSTALLATION_ID, true );

		?>
		<p class="description">
			<?php esc_html_e( 'After creating the app on GitHub, copy the generated credentials into the fields below, upload the private key, and save this connection.', WP2_UPDATE_TEXT_DOMAIN ); ?>
		</p>
		<table class="form-table">
			<tbody>
				<?php if ( ! empty( $organization ) ) : ?>
					<tr>
						<th><?php esc_html_e( 'GitHub Organization', WP2_UPDATE_TEXT_DOMAIN ); ?></th>
						<td>
							<code><?php echo esc_html( $organization ); ?></code>
							<p class="description"><?php esc_html_e( 'This app is configured to be owned by this organization.', WP2_UPDATE_TEXT_DOMAIN ); ?></p>
						</td>
					</tr>
				<?php endif; ?>
				<tr>
					<th><label for="_wp2_app_id"><?php esc_html_e( 'GitHub App ID', WP2_UPDATE_TEXT_DOMAIN ); ?></label></th>
					<td><input type="text" id="_wp2_app_id" name="_wp2_app_id" value="<?php echo esc_attr( get_post_meta( $post->ID, '_wp2_app_id', true ) ); ?>" class="widefat" /></td>
				</tr>
				<tr>
					<th><label for="_wp2_client_id"><?php esc_html_e( 'Client ID', WP2_UPDATE_TEXT_DOMAIN ); ?></label></th>
					<td><input type="text" id="_wp2_client_id" name="_wp2_client_id" value="<?php echo esc_attr( get_post_meta( $post->ID, '_wp2_client_id', true ) ); ?>" class="widefat" autocomplete="username" /></td>
				</tr>
				<tr>
					<th><label for="_wp2_client_secret"><?php esc_html_e( 'Client Secret', WP2_UPDATE_TEXT_DOMAIN ); ?></label></th>
					<td>
						<input type="password" id="_wp2_client_secret" name="_wp2_client_secret" value="" class="widefat" autocomplete="new-password" />
						<p class="description"><?php esc_html_e( 'Enter a new Client Secret to update. Leave blank to keep the existing one.', WP2_UPDATE_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="_wp2_private_key_file"><?php esc_html_e( 'Private Key (.pem)', WP2_UPDATE_TEXT_DOMAIN ); ?></label></th>
					<td>
						<?php if ( $private_key_set ) : ?>
							<p style="color:#28a745;font-weight:bold;"><?php esc_html_e( '✔️ Private key is set and saved.', WP2_UPDATE_TEXT_DOMAIN ); ?></p>
							<label><input type="checkbox" name="clear_wp2_private_key" value="1"> <?php esc_html_e( 'Clear the saved key to upload a new one.', WP2_UPDATE_TEXT_DOMAIN ); ?></label>
						<?php else : ?>
							<input type="file" id="_wp2_private_key_file" name="_wp2_private_key_file" class="widefat" accept=".pem" />
							<p class="description"><?php esc_html_e( 'Upload the .pem file generated by GitHub.', WP2_UPDATE_TEXT_DOMAIN ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><label for="_wp2_webhook_secret"><?php esc_html_e( 'Webhook Secret', WP2_UPDATE_TEXT_DOMAIN ); ?></label></th>
					<td><input type="text" id="_wp2_webhook_secret" name="_wp2_webhook_secret" value="<?php echo esc_attr( get_post_meta( $post->ID, '_wp2_webhook_secret', true ) ); ?>" class="widefat" /></td>
				</tr>
				<tr>
					<th><label for="_wp2_installation_id"><?php esc_html_e( 'Installation ID', WP2_UPDATE_TEXT_DOMAIN ); ?></label></th>
					<td>
						<input type="text" id="_wp2_installation_id" name="_wp2_installation_id" value="<?php echo esc_attr( get_post_meta( $post->ID, '_wp2_installation_id', true ) ); ?>" class="widefat" readonly />
						<p class="description"><?php esc_html_e( 'This is populated automatically when you install the app on a repository.', WP2_UPDATE_TEXT_DOMAIN ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php if ( $app_id_is_set && $private_key_set ) : ?>
			<div style="padding:10px; background:#f0f6fc; border:1px solid #ccd0d4; margin-top:20px; border-left-width: 4px; border-left-color: #0969da;">
				<h3><?php esc_html_e( 'Step 3: Install App on Repositories', WP2_UPDATE_TEXT_DOMAIN ); ?></h3>
				<p><?php esc_html_e( 'Now that your credentials are saved, you can install this app on your personal or organization\'s repositories to grant access.', WP2_UPDATE_TEXT_DOMAIN ); ?></p>
				<?php
				$site_hash        = substr( md5( (string) home_url() ), 0, 8 );
				$app_name         = 'WP2 Update (' . $site_hash . ') - Post ' . $post->ID;
				$app_slug         = sanitize_title( $app_name );
				$installation_url = 'https://github.com/apps/' . $app_slug . '/installations/new';
				?>
				<a href="<?php echo esc_url( $installation_url ); ?>" class="button button-primary" target="_blank" rel="noopener"><?php esc_html_e( 'Install GitHub App', WP2_UPDATE_TEXT_DOMAIN ); ?></a>
			</div>
		<?php endif; ?>

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
			'auto-draft' !== sanitize_text_field( $_POST['original_post_status'] )
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
		<p><?php esc_html_e( 'This data is automatically managed by the background sync process.', WP2_UPDATE_TEXT_DOMAIN ); ?></p>
		<table class="form-table">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Managing App', WP2_UPDATE_TEXT_DOMAIN ); ?></th>
					<td><?php echo $managing_app_id ? esc_html( get_the_title( (int) $managing_app_id ) ) : esc_html__( 'N/A', WP2_UPDATE_TEXT_DOMAIN ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Health Status', WP2_UPDATE_TEXT_DOMAIN ); ?></th>
					<td>
						<strong style="color: <?php echo ( 'ok' === $health_status ) ? '#28a745' : '#dc3545'; ?>;">
							<?php echo esc_html( ucfirst( (string) $health_status ) ); ?>
						</strong>
						<p><em><?php echo esc_html( (string) $health_message ); ?></em></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Last Synced', WP2_UPDATE_TEXT_DOMAIN ); ?></th>
					<td><?php echo $last_synced ? esc_html( wp_date( 'Y-m-d H:i:s', (int) $last_synced ) ) : esc_html__( 'Never', WP2_UPDATE_TEXT_DOMAIN ); ?></td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Renders the App Health metabox content.
	 *
	 * @param \WP_Post $post The current post object.
	 */
	public function render_app_health_metabox( \WP_Post $post ): void {
		$app_post_id = $post->ID;

		// Ensure the AppHealth service is available.
		if ( ! class_exists( '\WP2\Update\Core\Health\AppHealth' ) ) {
			echo '<p>' . esc_html__( 'AppHealth service is unavailable.', WP2_UPDATE_TEXT_DOMAIN ) . '</p>';
			return;
		}

		// Instantiate the AppHealth service.
		$github_service = new \WP2\Update\Core\API\Service();
		$app_health     = new \WP2\Update\Core\Health\AppHealth( $app_post_id, $github_service );

		// Run health checks and fetch the status.
		$app_health->run_checks();
		$health_status  = get_post_meta( $app_post_id, '_health_status', true );
		$health_message = get_post_meta( $app_post_id, '_health_message', true );

		// Display the health status and message.
		echo '<p><strong>' . esc_html__( 'Status:', WP2_UPDATE_TEXT_DOMAIN ) . '</strong> ' . esc_html( ucfirst( $health_status ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Message:', WP2_UPDATE_TEXT_DOMAIN ) . '</strong> ' . esc_html( $health_message ) . '</p>';
	}

	/**
	 * Renders the App Logs meta box content.
	 *
	 * @param \WP_Post $post The current post object.
	 */
	public function render_app_logs_metabox( \WP_Post $post ): void {
		$logs = Logger::get_logs_by_context( 'custom_columns' );

		// Filter logs specific to the current post ID.
		$filtered_logs = array_filter( $logs, function ( $log ) use ( $post ) {
			return strpos( $log['message'], "post {$post->ID}") !== false;
		});

		if ( empty( $filtered_logs ) ) {
			echo '<p>' . esc_html__( 'No logs available for this app.', WP2_UPDATE_TEXT_DOMAIN ) . '</p>';
			return;
		}

		echo '<ul style="max-height: 300px; overflow-y: auto;">';
		foreach ( $filtered_logs as $log ) {
			$timestamp = isset( $log['timestamp'] ) ? date( 'Y-m-d H:i:s', $log['timestamp'] ) : '';
			echo '<li>' . esc_html( $timestamp . ' - ' . $log['message'] ) . '</li>';
		}
		echo '</ul>';
	}

	/**
	 * @param int      $post_id
	 * @param \WP_Post $post
	 */
	public function save_metadata( int $post_id, \WP_Post $post ): void {
		if (
			'wp2_github_app' !== $post->post_type
			|| ! isset( $_POST['wp2_github_app_nonce'] )
			|| ! wp_verify_nonce( wp_unslash( $_POST['wp2_github_app_nonce'] ), 'wp2_github_app_save_meta' )
			|| ! current_user_can( 'edit_post', $post_id )
		) {
			return;
		}
		// Save the organization field, only present on the initial save.
		if ( isset( $_POST['_wp2_github_organization'] ) ) {
			update_post_meta( $post_id, '_wp2_github_organization', sanitize_text_field( wp_unslash( $_POST['_wp2_github_organization'] ) ) );
		}

		// Validate App ID
        if ( isset( $_POST['_wp2_app_id'] ) ) {
            $app_id = sanitize_text_field( wp_unslash( $_POST['_wp2_app_id'] ) );
            if ( ! preg_match( '/^\d+$/', $app_id ) ) {
                Logger::log( 'Invalid App ID provided: ' . $app_id, 'error', 'metadata' );
                return;
            }
            update_post_meta( $post_id, '_wp2_app_id', $app_id );
        }

        // Validate Installation ID
        if ( isset( $_POST['_wp2_installation_id'] ) ) {
            $installation_id = sanitize_text_field( wp_unslash( $_POST['_wp2_installation_id'] ) );
            if ( ! preg_match( '/^\d+$/', $installation_id ) ) {
                Logger::log( 'Invalid Installation ID provided: ' . $installation_id, 'error', 'metadata' );
                return;
            }
            update_post_meta( $post_id, '_wp2_installation_id', $installation_id );
        }

		update_post_meta( $post_id, '_wp2_webhook_secret', sanitize_text_field( wp_unslash( $_POST['_wp2_webhook_secret'] ?? '' ) ) );
		update_post_meta( $post_id, '_wp2_client_id', sanitize_text_field( wp_unslash( $_POST['_wp2_client_id'] ?? '' ) ) );

		if ( isset( $_POST['_wp2_client_secret'] ) && '' !== (string) $_POST['_wp2_client_secret'] ) {
			update_post_meta( $post_id, '_wp2_client_secret', SharedUtils::encrypt( sanitize_text_field( wp_unslash( (string) $_POST['_wp2_client_secret'] ) ) ) );
		}

		if ( isset( $_POST['clear_wp2_private_key'] ) && '1' === sanitize_text_field( $_POST['clear_wp2_private_key'] ) ) {
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

		$container = apply_filters( 'wp2_update_di_container', null );
		if ( $container && method_exists( $container, 'resolve' ) ) {
			$package_finder = $container->resolve( 'PackageFinder' );
			if ( $package_finder instanceof \WP2\Update\Core\Updates\PackageFinder ) {
				$package_finder->clear_cache();
			}
		}

		if ( class_exists( '\WP2\Update\Core\Tasks\Scheduler' ) ) {
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
				$new_columns['managing_app'] = __( 'Managing App', WP2_UPDATE_TEXT_DOMAIN );
				$new_columns['health_status'] = __( 'Health Status', WP2_UPDATE_TEXT_DOMAIN );
				$new_columns['last_synced'] = __( 'Last Synced', WP2_UPDATE_TEXT_DOMAIN );
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
				echo $app_post_id ? esc_html( get_the_title( $app_post_id ) ) : esc_html__( 'N/A', WP2_UPDATE_TEXT_DOMAIN );
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
				echo $timestamp ? esc_html( wp_date( 'Y-m-d H:i:s', $timestamp ) ) : esc_html__( 'Never', WP2_UPDATE_TEXT_DOMAIN );
				break;
		}
	}

	/**
     * Adds GitHub App data as columns for quick view.
     *
     * @param array<string,string> $columns
     * @return array<string,string>
     */
    public function add_github_app_custom_columns( array $columns ): array {
        $new_columns = [];
        foreach ( $columns as $key => $title ) {
            $new_columns[ $key ] = $title;
            if ( 'title' === $key ) {
                $new_columns['app_id'] = __( 'App ID', WP2_UPDATE_TEXT_DOMAIN );
                $new_columns['client_id'] = __( 'Client ID', WP2_UPDATE_TEXT_DOMAIN );
                $new_columns['installation_id'] = __( 'Installation ID', WP2_UPDATE_TEXT_DOMAIN );
            }
        }
        return $new_columns;
    }

    /**
     * Populates GitHub App custom columns with data.
     *
     * @param string $column
     * @param int    $post_id
     */
    public function populate_github_app_custom_columns( string $column, int $post_id ): void {
        static $rendered_columns = [];

        if (isset($rendered_columns[$post_id][$column])) {
            return; // Prevent duplicate rendering for the same column and post ID.
        }

        switch ( $column ) {
            case 'app_id':
                $app_id = get_post_meta( $post_id, '_wp2_app_id', true );
                Logger::log_debug( sprintf( 'App ID for post %d: %s', $post_id, (string) $app_id ), 'custom_columns' );
                echo esc_html( $app_id );
                break;

            case 'client_id':
                $client_id = get_post_meta( $post_id, '_wp2_client_id', true );
                Logger::log_debug( sprintf( 'Client ID for post %d: %s', $post_id, (string) $client_id ), 'custom_columns' );
                echo esc_html( $client_id );
                break;

            case 'installation_id':
                $installation_id = get_post_meta( $post_id, '_wp2_installation_id', true );
                Logger::log_debug( sprintf( 'Installation ID for post %d: %s', $post_id, (string) $installation_id ), 'custom_columns' );
                echo esc_html( $installation_id );
                break;
        }

        $rendered_columns[$post_id][$column] = true; // Mark this column as rendered.
    }

	/**
	 * Success notice after automated setup.
	 */
	public function show_automated_setup_success_notice(): void {
		if ( isset( $_GET['message'] ) && 'app_created' === sanitize_text_field( $_GET['message'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' .
				esc_html__( 'Success! Your GitHub App has been created. Please copy the credentials into the form below.', WP2_UPDATE_TEXT_DOMAIN ) .
				'</p></div>';
		}
	}

	/**
	 * Success notice after installation ID is saved.
	 */
	public function show_installation_saved_notice(): void {
		if ( isset( $_GET['message'] ) && 'installation_saved' === sanitize_text_field( $_GET['message'] ) ) {
			$timestamp = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
			echo '<div class="notice notice-success is-dismissible"><p>' .
				esc_html__( 'Success! The Installation ID has been saved.', WP2_UPDATE_TEXT_DOMAIN ) . '<br>' .
				esc_html__( 'Timestamp:', WP2_UPDATE_TEXT_DOMAIN ) . ' ' . esc_html( $timestamp ) .
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
            $message = __( 'An unknown error occurred during the automated setup.', WP2_UPDATE_TEXT_DOMAIN );
            if ( 'invalid_request' === $error_code ) {
                $message = __( 'Invalid callback request or session expired. Please try again.', WP2_UPDATE_TEXT_DOMAIN );
            }
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
        }
    }

	/**
	 * @param array<string,mixed> $payload
	 */
	public function handle_github_installation_event( array $payload ): void {
		// Validate payload for GitHub installation event.
        if ( empty( $payload['installation']['id'] ) || empty( $payload['installation']['app_id'] ) ) {
            Logger::log_debug( 'Invalid payload received for GitHub installation event.', 'webhook' );
            return;
        }

        $installation_id = absint( $payload['installation']['id'] );
        $app_id          = absint( $payload['installation']['app_id'] );

        Logger::log_debug( 'Handling GitHub installation event. Payload keys: ' . implode( ', ', array_keys( (array) $payload ) ), 'webhook' );
        Logger::log_debug( sprintf( 'Installation ID %s associated with App ID %s.', (string) $installation_id, (string) $app_id ), 'webhook' );

        $app_query = new \WP_Query(
            [
                'post_type'      => 'wp2_github_app',
                'meta_key'       => '_wp2_app_id',
                'meta_value'     => $app_id,
                'posts_per_page' => 1,
                'fields'         => 'ids',
            ]
        );

        if ( empty( $app_query->posts ) ) {
            Logger::log_debug( 'No app post found for App ID ' . $app_id, 'webhook' );
            return;
        }

        $app_post_id = $app_query->posts[0];
        update_post_meta( $app_post_id, '_wp2_installation_id', $installation_id );
	}

	public function handle_update_check(): void {
        // Verify nonce for security.
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wp2_update_check')) {
            wp_die(__('Invalid request.', WP2_UPDATE_TEXT_DOMAIN ));
        }

        // Perform the check logic here.
        $results = $this->perform_update_check();

        // Store results in a transient to display in admin notice.
        set_transient('wp2_update_check_results', $results, 60);

        // Redirect back to the admin page securely.
        $referer = wp_get_referer();
        if ($referer) {
            wp_safe_redirect(add_query_arg('wp2_update_check', '1', $referer));
        } else {
            wp_safe_redirect(admin_url());
        }
        exit;
    }

    private function perform_update_check(): array {
        // Simulate performing a check and returning results.
        return [
            'status' => 'success',
            'message' => __('Update check completed successfully.', WP2_UPDATE_TEXT_DOMAIN ),
        ];
    }

    /**
     * Displays the results of the update check in a dismissable admin notice.
     */
    public function show_update_check_results(): void {
        if (!get_transient('wp2_update_check_results')) {
            return;
        }

        $results = get_transient('wp2_update_check_results');
        delete_transient('wp2_update_check_results');

        $class = $results['status'] === 'success' ? 'notice-success' : 'notice-error';
        $message = $results['message'];

        printf('<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr($class), esc_html($message));
    }
}
