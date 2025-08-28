<?php
// src/Packages/Plugins/Admin.php

namespace WP2\Update\Packages\Plugins;

use WP2\Update\Core\Admin as AdminInterface;
use WP2\Update\Helpers\Admin as AdminHelpers;
use WP2\Update\Helpers\Github;
use WP2\Update\Utils\Log;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin implements AdminInterface {
	private $managed_plugins;
	private const TYPE = 'plugin';

	public function __construct( array $managed_plugins = [] ) {
		$this->managed_plugins = $managed_plugins;
		add_action( 'admin_init', [ $this, 'handle_force_check_action' ] );
		add_action( 'admin_post_wp2-plugin-install', [ $this, 'handle_install_action' ] );
	}

	public function register( array $managed ): void {
		$this->managed_plugins = $managed;
	}
	public function enqueue_assets() {
		wp_enqueue_script(
			'wp2-update-admin',
			plugins_url( 'wp2_update/assets/admin.js' ),
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
			__( 'WP2 Plugin Updater', 'wp2-update' ),
			self::TYPE,
			$this->managed_plugins,
			function ($plugin_data) {
				echo '<div class="wp2-plugin-health-check" data-slug="' . esc_attr( $plugin_data['slug'] ) . '"></div>';
			},
			[ $this, 'display_releases' ]
		);
		// AJAX handler for plugin health check
		add_action( 'wp_ajax_wp2_plugin_health_check', function () {
			check_ajax_referer( 'wp2_plugin_health_check' );
			$slug    = isset( $_POST['slug'] ) ? sanitize_key( $_POST['slug'] ) : '';
			$plugins = ( new \WP2\Update\Packages\Plugins\Discovery() )->detect();
			if ( ! isset( $plugins[ $slug ] ) ) {
				wp_send_json_error( [ 'html' => '<span style="color:red;">Plugin not found.</span>' ] );
			}
			ob_start();
			( new \WP2\Update\Packages\Plugins\Admin( [] ) )->run_health_check( $plugins[ $slug ] );
			$html = ob_get_clean();
			wp_send_json_success( [ 'html' => $html ] );
		} );
	}
	public function handle_install_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Permission denied.', 'wp2-update' ) );
		}
		$plugin_file = sanitize_text_field( $_POST['plugin'] ?? '' );
		$version     = sanitize_text_field( $_POST['version'] ?? '' );
		check_admin_referer( 'wp2-plugin-install-' . $plugin_file . '-' . $version );
		$package_data = $this->managed_plugins[ $plugin_file ] ?? null;
		if ( ! $package_data || ! $version ) {
			wp_die( __( 'Missing required fields.', 'wp2-update' ) );
		}
		$release = Github::get_release_by_tag( $package_data['repo'], $version );
		$zip_url = Github::find_zip_asset( $release );
		if ( ! $zip_url ) {
			wp_die( __( 'No ZIP asset found for requested version.', 'wp2-update' ) );
		}
		$upgrader = \WP2\Update\Helpers\UpgraderFactory::create( self::TYPE );
		$result = $upgrader->install( $package_data, $version, $zip_url );
		if ( is_wp_error( $result ) ) {
			Log::add( 'Install failed: ' . $result->get_error_message(), 'error', 'plugin-update' );
			wp_safe_redirect( admin_url( 'admin.php?page=wp2-update-dashboard&error=unzip&type=plugin' ) );
		} else {
			Log::add( "Plugin '{$plugin_file}' updated to {$version}.", 'success', 'plugin-update' );
			wp_clean_plugins_cache( true );
			wp_safe_redirect( admin_url( 'admin.php?page=wp2-update-dashboard&success=installed&version=' . urlencode( $version ) . '&type=plugin' ) );
		}
		exit;
	}
	public function handle_force_check_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( isset( $_GET['page'], $_GET['force-check'], $_GET['type'] ) && 'wp2-plugins-update' === $_GET['page'] && '1' === $_GET['force-check'] && self::TYPE === $_GET['type'] ) {
			check_admin_referer( 'wp2-plugin-force-check' );
			Log::add( 'Force plugin update check triggered by admin.', 'info', 'plugin-update' );
			delete_site_transient( 'update_plugins' );
			if ( function_exists( 'wp_update_plugins' ) ) {
				wp_update_plugins();
			}
			wp_safe_redirect( admin_url( 'admin.php?page=wp2-update-dashboard&purged=1&type=' . self::TYPE ) );
			exit;
		}
	}

	private function get_plugin_version( $plugin_file ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		return $plugins[ $plugin_file ]['Version'] ?? '';
	}

	public function run_health_check( array $plugin_data ) {
		$current_version  = $this->get_plugin_version( $plugin_data['file'] );
		$update_data      = get_site_transient( 'update_plugins' );
		$update_available = isset( $update_data->response[ $plugin_data['file'] ] );
		?>
		<h2><?php esc_html_e( 'Health Check Status', 'wp2-update' ); ?></h2>
		<table class="widefat striped">
			<tbody>
				<tr>
					<td><strong><?php esc_html_e( 'Current Installed Version', 'wp2-update' ); ?></strong></td>
					<td><?php echo esc_html( $current_version ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Repository', 'wp2-update' ); ?></strong></td>
					<td><a href="https://github.com/<?php echo esc_attr( $plugin_data['repo'] ); ?>" target="_blank"
							rel="noopener noreferrer"><?php echo esc_html( $plugin_data['repo'] ); ?></a></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Update Status', 'wp2-update' ); ?></strong></td>
					<td>
						<?php if ( $update_available ) : ?>
							<span style="color: green;">
								✅
								<?php printf( esc_html__( 'New version %s is available.', 'wp2-update' ), '<strong>' . esc_html( $update_data->response[ $plugin_data['file'] ]->new_version ) . '</strong>' ); ?>
							</span>
							<a href="<?php echo esc_url( wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . $plugin_data['file'] ), 'upgrade-plugin_' . $plugin_data['file'] ) ); ?>"
								class="button button-primary" style="margin-left: 10px;">
								<?php esc_html_e( 'Update Now', 'wp2-update' ); ?>
							</a>
						<?php else : ?>
							<span>ℹ️ <?php esc_html_e( 'Your plugin is up to date.', 'wp2-update' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	public function display_releases( array $plugin_data ) {
		$releases = Github::get_releases( $plugin_data['repo'] );
		?>
		<h2><?php esc_html_e( 'Available Versions', 'wp2-update' ); ?></h2>
		<?php
		if ( empty( $releases ) ) {
			echo '<p>' . esc_html__( 'Could not retrieve releases from GitHub.', 'wp2-update' ) . '</p>';
			return;
		}
		foreach ( $releases as $release ) :
			$zip_url = '';
			if ( isset( $release->assets ) && is_array( $release->assets ) ) {
				foreach ( $release->assets as $asset ) {
					if ( isset( $asset->browser_download_url ) && preg_match( '/\.zip$/', $asset->browser_download_url ) ) {
						$zip_url = $asset->browser_download_url;
						break;
					}
				}
			}
			?>
			<tr>
				<td>
					<strong><?php echo esc_html( $release->tag_name ); ?></strong>
					<?php if ( ! empty( $release->prerelease ) ) : ?>
						<span style="color:#d63638;">(Pre-release)</span>
					<?php endif; ?>
					<?php if ( ! empty( $release->body ) ) : ?>
						<div
							style="font-size:0.95em;color:#666;margin-top:4px;max-width:400px;white-space:pre-line;overflow:hidden;text-overflow:ellipsis;">
							<?php
							$body      = $release->body;
							$short     = mb_substr( $body, 0, 200 );
							$show_more = mb_strlen( $body ) > 200;
							?>
							<span class="wp2-release-short">
								<?php echo esc_html( $short ); ?>				<?php if ( $show_more )
														 echo '…'; ?>
							</span>
							<?php if ( $show_more ) : ?>
								<a href="#" class="wp2-show-more" onclick="jQuery(this).hide().next().show();return false;">Show more</a>
								<span class="wp2-release-full" style="display:none;white-space:pre-line;">
									<?php echo esc_html( $body ); ?>
								</span>
							<?php endif; ?>
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
			</tr>
		<?php endforeach; ?>
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

// TODO: Add changelogs, diffs, and richer release notes display for plugins
