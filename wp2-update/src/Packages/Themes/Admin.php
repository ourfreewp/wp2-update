<?php
namespace WP2\Update\Packages\Themes;

use WP2\Update\Core\Admin as AdminInterface;
use WP2\Update\Helpers\Admin as AdminHelpers;
use WP2\Update\Utils\Log;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin implements AdminInterface {
	private $managed_themes;

	public function __construct( array $managed_themes ) {
		$this->managed_themes = $managed_themes;
		add_action( 'admin_init', [ $this, 'handle_force_check_action' ] );
		add_action( 'admin_post_wp2-theme-install', [ $this, 'handle_install_action' ] );
		$this->register_dashboard_widget();
	}

	public function register( array $managed ): void {
		$this->managed_themes = $managed;
	}

	public function add_dashboard_widget() {
		wp_add_dashboard_widget(
			'wp2_update_status',
			__( 'WP2 Update Status', 'wp2-update' ),
			[ $this, 'render_dashboard_widget' ]
		);
	}

	public function render_dashboard_widget() {
		$themes = ( new \WP2\Update\Packages\Themes\Discovery() )->detect();
		echo '<ul style="margin:0;">';
		foreach ( $themes as $slug => $theme ) {
			$releases = \WP2\Update\Helpers\Github::get_releases( $theme['repo'] );
			$current  = wp_get_theme( $slug )->get( 'Version' );
			$latest   = ! empty( $releases ) ? $releases[0]->tag_name : 'N/A';
			$status   = version_compare( $current, $latest, '>=' ) ? '<span style="color:green;">Up to date</span>' : '<span style="color:#d63638;">Update available</span>';
			echo '<li><strong>' . esc_html( $theme['name'] ) . '</strong>: ' . esc_html( $status ) . ' (Current: ' . esc_html( $current ) . ', Latest: ' . esc_html( $latest ) . ')</li>';
		}
		echo '</ul>';
	}

	public function register_dashboard_widget() {
		if ( is_multisite() && is_network_admin() ) {
			add_action( 'wp_network_dashboard_setup', [ $this, 'add_dashboard_widget' ] );
		} else {
			add_action( 'wp_dashboard_setup', [ $this, 'add_dashboard_widget' ] );
		}
	}

	public function enqueue_assets() {
		wp_enqueue_script(
			'wp2-update-admin',
			plugins_url( 'wp2-update/assets/admin.js' ),
			[ 'jquery' ],
			null,
			true
		);
		wp_localize_script( 'wp2-update-admin', 'wp2ThemeAdmin', [
			'nonce' => wp_create_nonce( 'wp2_theme_health_check' ),
		] );
		wp_localize_script( 'wp2-update-admin', 'wp2PluginAdmin', [
			'nonce' => wp_create_nonce( 'wp2_plugin_health_check' ),
		] );
		wp_localize_script( 'wp2-update-admin', 'wp2DaemonAdmin', [
			'nonce' => wp_create_nonce( 'wp2_daemon_health_check' ),
		] );
	}

	public function render_page(): void {
		AdminHelpers::render_page(
			__( 'WP2 Theme Updater', 'wp2-update' ),
			'theme',
			$this->managed_themes,
			function ($theme_data) {
				echo '<div class="wp2-theme-health-check" data-slug="' . esc_attr( $theme_data['slug'] ) . '"></div>';
			},
			function($theme_data) {
				// Call the releases display logic
				$this->display_releases($theme_data);
			}
		);
		// AJAX handler for theme health check
		add_action( 'wp_ajax_wp2_theme_health_check', function () {
			check_ajax_referer( 'wp2_theme_health_check' );
			$slug   = isset( $_POST['slug'] ) ? sanitize_key( $_POST['slug'] ) : '';
			$themes = ( new \WP2\Update\Packages\Themes\Discovery() )->detect();
			if ( ! isset( $themes[ $slug ] ) ) {
				wp_send_json_error( [ 'html' => '<span style="color:red;">Theme not found.</span>' ] );
			}
			ob_start();
			( new \WP2\Update\Packages\Themes\Admin( [] ) )->run_health_check( $themes[ $slug ] );
			$html = ob_get_clean();
			wp_send_json_success( [ 'html' => $html ] );
		} );
	}



	public function handle_force_check_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( isset( $_GET['page'], $_GET['force-check'], $_GET['type'] ) && 'wp2-update' === $_GET['page'] && '1' === $_GET['force-check'] && 'theme' === $_GET['type'] ) {
			check_admin_referer( 'wp2-theme-force-check' );
			Log::add( 'Force theme update check triggered by admin.', 'info', 'theme-update' );
			delete_site_transient( 'update_themes' );
			if ( function_exists( 'wp_update_themes' ) ) {
				wp_update_themes();
			}
			wp_safe_redirect( admin_url( 'tools.php?page=wp2-update&purged=1&type=theme' ) );
			exit;
		}
	}

	public function handle_install_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Permission denied.', 'wp2-update' ) );
		}
		if ( ! isset( $_POST['theme'], $_POST['version'] ) ) {
			wp_die( __( 'Missing required fields.', 'wp2-update' ) );
		}
		$theme_slug = sanitize_key( wp_unslash( $_POST['theme'] ) );
		$version    = sanitize_text_field( wp_unslash( $_POST['version'] ) );
		check_admin_referer( 'wp2-theme-install-' . $theme_slug . '-' . $version );
		if ( ! isset( $this->managed_themes[ $theme_slug ] ) ) {
			Log::add( 'Install failed: Theme slug not found.', 'error', 'theme-update' );
			wp_safe_redirect( admin_url( 'admin.php?page=wp2-update-dashboard&error=notfound&type=theme' ) );
			exit;
		}
		$repo    = $this->managed_themes[ $theme_slug ]['repo'];
		$context = [ 
			'theme_slug'    => $theme_slug,
			'theme_repo'    => $repo,
			'theme_version' => $version,
		];
		do_action( 'wp2_update_theme_pre_install', $context );
		// Check GitHub App authentication before proceeding
		$app_id = defined('WP2_GITHUB_APP_ID') ? WP2_GITHUB_APP_ID : null;
		$install_id = defined('WP2_GITHUB_INSTALLATION_ID') ? WP2_GITHUB_INSTALLATION_ID : null;
		$private_key_path = defined('WP2_GITHUB_PRIVATE_KEY_PATH') ? WP2_GITHUB_PRIVATE_KEY_PATH : null;
		if ( ! $app_id || ! $install_id || ! $private_key_path || ! file_exists($private_key_path) ) {
			Log::add( 'Install failed: GitHub App authentication is missing or invalid.', 'error', 'theme-update' );
			set_transient( 'wp2_update_download_error', __( 'GitHub App authentication is missing or invalid. Please check your wp-config.php.', 'wp2-update' ), 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=wp2-update-dashboard&error=auth&type=theme' ) );
			exit;
		}
		$upgrader = \WP2\Update\Helpers\UpgraderFactory::create( 'theme' );
		// Find the ZIP URL for the requested version
		$releases = \WP2\Update\Helpers\Github::get_releases( $repo );
		$zip_url = '';
		foreach ($releases as $release) {
			if ($release->tag_name === $version && !empty($release->assets)) {
				foreach ($release->assets as $asset) {
					if (isset($asset->content_type) && $asset->content_type === 'application/zip') {
						$zip_url = $asset->browser_download_url;
						break 2;
					}
				}
			}
		}
		$package_data = [
			'slug' => $theme_slug,
			'repo' => $repo,
			'version' => $version,
		];
		$result = $upgrader->install($package_data, $version, $zip_url);
		if ( is_wp_error( $result ) ) {
			$error_message = $result->get_error_message();
			Log::add( "Install failed: {$error_message}", 'error', 'theme-update' );
			set_transient( 'wp2_update_download_error', $error_message, 60 );
			do_action( 'wp2_update_theme_install_failed', $context );
			wp_safe_redirect( admin_url( 'admin.php?page=wp2-update-dashboard&error=unzip&type=theme' ) );
			exit;
		}
		if ( $result === true ) {
			Log::add( "Theme '{$theme_slug}' successfully installed/rolled back to version {$version}.", 'success', 'theme-update' );
			delete_site_transient( 'update_themes' );
			do_action( 'wp2_update_theme_post_install', $context );
			wp_safe_redirect( admin_url( 'admin.php?page=wp2-update-dashboard&success=installed&version=' . urlencode( $version ) . '&type=theme' ) );
			exit;
		} else {
			Log::add( 'Install failed: Unknown error, no changes made.', 'error', 'theme-update' );
			wp_safe_redirect( admin_url( 'admin.php?page=wp2-update-dashboard&error=install&type=theme' ) );
			exit;
		}
	}

	private function display_notices() {
		$rate_limit_error = get_transient( 'wp2_update_rate_limit_error' );
		if ( $rate_limit_error ) {
			echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'GitHub API Rate Limit:', 'wp2-update' ) . '</strong> ' . esc_html( $rate_limit_error ) . '</p></div>';
			delete_transient( 'wp2_update_rate_limit_error' );
		}
		if ( isset( $_GET['tab'] ) ) {
			$no_asset_error = get_transient( 'wp2_update_no_asset_error_' . sanitize_key( $_GET['tab'] ) );
			if ( $no_asset_error ) {
				echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Release Error:', 'wp2-update' ) . '</strong> ' . esc_html( $no_asset_error ) . '</p></div>';
				delete_transient( 'wp2_update_no_asset_error_' . sanitize_key( $_GET['tab'] ) );
			}
		}
		if ( isset( $_GET['purged'] ) && ( ! isset( $_GET['type'] ) || $_GET['type'] === 'theme' ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Theme update cache purged. A fresh check will run.', 'wp2-update' ) . '</p></div>';
		}
		if ( isset( $_GET['success'], $_GET['version'] ) && 'installed' === $_GET['success'] && ( ! isset( $_GET['type'] ) || $_GET['type'] === 'theme' ) ) {
			$version = sanitize_text_field( wp_unslash( $_GET['version'] ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( esc_html__( 'Theme successfully installed version %s.', 'wp2-update' ), '<strong>' . esc_html( $version ) . '</strong>' ) . '</p></div>';
		}
		if ( isset( $_GET['error'] ) && ( ! isset( $_GET['type'] ) || $_GET['type'] === 'theme' ) ) {
			$error_message = get_transient( 'wp2_update_download_error' ) ?: __( 'An unknown error occurred.', 'wp2-update' );
			delete_transient( 'wp2_update_download_error' );
			echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Action Failed:', 'wp2-update' ) . '</strong> ' . esc_html( $error_message ) . '</p></div>';
		}
	}

	private function run_health_check( array $theme_data ) {
		$github_pat       = defined( 'WP2_GITHUB_PAT' ) ? WP2_GITHUB_PAT : null;
		$current_version  = wp_get_theme( $theme_data['slug'] )->get( 'Version' );
		$update_data      = get_site_transient( 'update_themes' );
		$update_available = isset( $update_data->response[ $theme_data['slug'] ] );
		?>
		<h2><?php esc_html_e( 'Health Check Status', 'wp2-update' ); ?></h2>
		<table class="widefat striped">
			<tbody>
				<tr>
					<td><strong><?php esc_html_e( 'GitHub PAT Constant', 'wp2-update' ); ?></strong></td>
					<td><?php echo $github_pat ? '<span style="color:green;">✔</span>' : '<span style="color:#d63638;">✘</span>'; ?>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Current Installed Version', 'wp2-update' ); ?></strong></td>
					<td><?php echo esc_html( $current_version ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Repository', 'wp2-update' ); ?></strong></td>
					<td><a href="https://github.com/<?php echo esc_attr( $theme_data['repo'] ); ?>"
							target="_blank"><?php echo esc_html( $theme_data['repo'] ); ?></a></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Connection to GitHub API', 'wp2-update' ); ?></strong></td>
					<td>
						<?php
						$args = [ 
							'timeout' => 15,
							'headers' => [ 'User-Agent' => 'WP2Update/1.0 (+https://webmultipliers.com)' ],
						];
						if ( $github_pat ) {
							$args['headers']['Authorization'] = "token {$github_pat}";
						}
						$response = wp_remote_get( "https://api.github.com/repos/{$theme_data['repo']}", $args );
						if ( is_wp_error( $response ) ) {
							echo '<span style="color: red;">❌ ' . sprintf( esc_html__( 'Connection Failed: %s', 'wp2-update' ), esc_html( $response->get_error_message() ) ) . '</span>';
						} elseif ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
							echo '<span style="color: red;">❌ ' . sprintf( esc_html__( 'Connection Failed (Code: %s)', 'wp2-update' ), esc_html( wp_remote_retrieve_response_code( $response ) ) ) . '</span>';
						} else {
							echo '<span style="color: green;">✅ ' . esc_html__( 'Connected Successfully', 'wp2-update' ) . '</span>';
							$remaining = wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' );
							if ( '' !== $remaining ) {
								echo ' <span class="description">(' . esc_html( $remaining ) . ' ' . esc_html__( 'API calls remaining', 'wp2-update' ) . ')</span>';
							}
						}
						?>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Update Status', 'wp2-update' ); ?></strong></td>
					<td>
						<?php if ( $update_available ) : ?>
							<span style="color: green;">
								✅ <?php esc_html_e( 'New version', 'wp2-update' ); ?>
								<strong><?php echo esc_html( $update_data->response[ $theme_data['slug'] ]['new_version'] ); ?></strong>
								<?php esc_html_e( 'is available.', 'wp2-update' ); ?>
							</span>
							<a href="<?php echo esc_url( wp_nonce_url( self_admin_url( 'update.php?action=upgrade-theme&theme=' . $theme_data['slug'] ), 'upgrade-theme_' . $theme_data['slug'] ) ); ?>"
								class="button button-primary" style="margin-left: 10px;">
								<?php esc_html_e( 'Update Now', 'wp2-update' ); ?>
							</a>
						<?php else : ?>
							<span>ℹ️ <?php esc_html_e( 'Your theme is up to date.', 'wp2-update' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	private function display_releases( array $theme_data ) {
		$github_pat = defined( 'WP2_GITHUB_PAT' ) ? WP2_GITHUB_PAT : null;
		$args       = [ 
			'timeout' => 15,
			'headers' => [ 'User-Agent' => 'WP2Update/1.0 (+https://webmultipliers.com)' ],
		];
		if ( $github_pat ) {
			$args['headers']['Authorization'] = "token {$github_pat}";
		}
		$response = wp_remote_get( "https://api.github.com/repos/{$theme_data['repo']}/releases?per_page=10", $args );
		?>
		<h2><?php esc_html_e( 'Available Versions', 'wp2-update' ); ?></h2>
		<?php
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			echo '<p>' . esc_html__( 'Could not retrieve releases from GitHub.', 'wp2-update' ) . '</p>';
			return;
		}
		$releases = json_decode( wp_remote_retrieve_body( $response ) );
		if ( empty( $releases ) ) {
			echo '<p>' . esc_html__( 'No releases found.', 'wp2-update' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Version', 'wp2-update' ); ?></th>
					<th><?php esc_html_e( 'Release Date', 'wp2-update' ); ?></th>
					<th><?php esc_html_e( 'Download Link', 'wp2-update' ); ?></th>
					<th><?php esc_html_e( 'Action', 'wp2-update' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$current_version = wp_get_theme( $theme_data['slug'] )->get( 'Version' );
				foreach ( $releases as $release ) :
					$zip_url = '';
					$has_zip = false;
					if ( ! empty( $release->assets ) ) {
						foreach ( $release->assets as $asset ) {
							if ( isset( $asset->content_type ) && 'application/zip' === $asset->content_type ) {
								$zip_url = $asset->browser_download_url ?? $asset->url ?? '';
								$has_zip = true;
								break;
							}
						}
					}
					$release_version      = preg_replace( '/^v/i', '', $release->tag_name );
					$current_version_norm = preg_replace( '/^v/i', '', $current_version );
					$is_current           = version_compare( $release_version, $current_version_norm, '==' );
					$action_label         = $is_current ? __( 'Reinstall', 'wp2-update' ) : ( version_compare( $release_version, $current_version_norm, '<' ) ? __( 'Rollback', 'wp2-update' ) : __( 'Upgrade', 'wp2-update' ) );
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $release->tag_name ); ?></strong>
							<?php if ( ! empty( $release->prerelease ) ) : ?>
								<span style="color:#d63638;">(Pre-release)</span>
								<?php if ( ! $has_zip ) : ?>
									<div style="color:#d63638;font-size:0.95em;">⚠️
										<?php esc_html_e( 'No ZIP asset for this pre-release.', 'wp2-update' ); ?></div>
								<?php endif; ?>
							<?php endif; ?>
							<?php if ( ! empty( $release->body ) ) : ?>
								<button class="button button-small wp2-view-details-button" style="margin-top:4px;">View
									Details</button>
								<div class="wp2-release-notes" style="display:none;">
									<?php echo wpautop( esc_html( $release->body ) ); ?>
								</div>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( date( 'Y-m-d', strtotime( $release->published_at ) ) ); ?></td>
						<td>
							<?php if ( $zip_url ) : ?>
								<a href="<?php echo esc_url( $zip_url ); ?>" target="_blank">ZIP</a>
							<?php else : ?>
								<span style="color:#d63638;">—</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $zip_url ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
									style="display:inline;">
									<input type="hidden" name="action" value="wp2-theme-install">
									<input type="hidden" name="theme" value="<?php echo esc_attr( $theme_data['slug'] ); ?>">
									<input type="hidden" name="version" value="<?php echo esc_attr( $release->tag_name ); ?>">
									<?php wp_nonce_field( 'wp2-theme-install-' . $theme_data['slug'] . '-' . $release->tag_name ); ?>
									<button class="button" type="submit"><?php echo esc_html( $action_label ); ?></button>
								</form>
							<?php else : ?>
								<span style="color:#aaa;">N/A</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function display_logs() {
		$logs = Log::get_logs();
		?>
		<h2><span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Recent Events', 'wp2-update' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th style="width: 200px;"> <?php esc_html_e( 'Timestamp', 'wp2-update' ); ?> </th>
					<th> <?php esc_html_e( 'Event', 'wp2-update' ); ?> </th>
					<th> <?php esc_html_e( 'Context', 'wp2-update' ); ?> </th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $logs ) ) : ?>
					<tr>
						<td colspan="3"> <?php esc_html_e( 'No events have been logged yet.', 'wp2-update' ); ?> </td>
					</tr>
				<?php else : ?>
					<?php foreach ( $logs as $log ) : ?>
						<tr>
							<td> <?php echo esc_html( date( 'Y-m-d H:i:s', $log['timestamp'] ) ); ?> </td>
							<td> <?php echo esc_html( $log['message'] ); ?> </td>
							<td> <?php echo esc_html( $log['context'] ); ?> </td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	private function display_release_details($release) {
		echo '<div class="wp2-release-details">';
		if (!empty($release->body)) {
			echo '<h4>Changelog</h4>';
			echo wpautop(esc_html($release->body));
		}
		if (!empty($release->diff_url)) {
			echo '<h4>Diff</h4>';
			echo '<a href="' . esc_url($release->diff_url) . '" target="_blank">View Diff</a>';
		}
		echo '</div>';
	}
}

// TODO: Add changelogs, diffs, and richer release notes display for themes
