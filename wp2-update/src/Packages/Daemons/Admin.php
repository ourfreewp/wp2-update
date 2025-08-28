<?php
// src/Packages/Daemons/Admin.php

namespace WP2\Update\Packages\Daemons;

use WP2\Update\Core\Admin as AdminInterface;
use WP2\Update\Helpers\Admin as AdminHelpers;
use WP2\Update\Utils\Log;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin implements AdminInterface {
	private $managed_daemons;

	public function render_page(): void {
		// Implement the page rendering logic here or leave empty if not needed yet.
		echo '<h2>' . esc_html__('Daemon Admin Page', 'wp2-update') . '</h2>';
	}

	public function __construct() {
		// The register method will be called later to set the managed items.
		$this->register_dashboard_widget();
	}

	public function register(array $managed): void {
		$this->managed_daemons = $managed;
	}

	public function add_dashboard_widget() {
		wp_add_dashboard_widget(
			'wp2_update_daemon_status',
			__( 'WP2 Daemon Update Status', 'wp2-update' ),
			[ $this, 'render_dashboard_widget' ]
		);
	}

	public function render_dashboard_widget() {
		$daemons = (new \WP2\Update\Packages\Daemons\Discovery())->detect();
		echo '<ul style="margin:0;">';
		foreach ($daemons as $slug => $daemon) {
			$releases = \WP2\Update\Helpers\Github::get_releases($daemon['repo']);
			$current = $daemon['version'] ?? 'N/A';
			$latest = !empty($releases) ? $releases[0]->tag_name : 'N/A';
			$status = version_compare($current, $latest, '>=') ? '<span style="color:green;">Up to date</span>' : '<span style="color:#d63638;">Update available</span>';
			echo '<li><strong>' . esc_html($daemon['name']) . '</strong>: ' . esc_html($status) . ' (Current: ' . esc_html($current) . ', Latest: ' . esc_html($latest) . ')</li>';
		}
		echo '</ul>';
	}
	public function handle_install_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Permission denied.', 'wp2-update' ) );
		}
		$daemon_file = sanitize_text_field( $_POST['daemon'] ?? '' );
		$version     = sanitize_text_field( $_POST['version'] ?? '' );
		check_admin_referer( 'wp2-daemon-install-' . $daemon_file . '-' . $version );
		$package_data = $this->managed_daemons[ $daemon_file ] ?? null;
		if ( ! $package_data || ! $version ) {
			Log::add( 'Install failed: Daemon slug or version missing.', 'error', 'daemon-update' );
			wp_safe_redirect( admin_url( 'admin.php?page=wp2-update-dashboard&error=notfound&type=daemon' ) );
			exit;
		}
		$release = \WP2\Update\Helpers\Github::get_release_by_tag( $package_data['repo'], $version );
		if ( is_wp_error( $release ) ) {
			Log::add( 'Install failed: Release not found or GitHub API error.', 'error', 'daemon-update' );
			wp_safe_redirect( admin_url( 'admin.php?page=wp2-update-dashboard&error=notfound&type=daemon' ) );
			exit;
		}
		$zip_url = \WP2\Update\Helpers\Github::find_zip_asset( $release );
		if ( ! $zip_url ) {
			Log::add( 'Install failed: ZIP asset not found for release.', 'error', 'daemon-update' );
			wp_safe_redirect( admin_url( 'admin.php?page=wp2-update-dashboard&error=unzip&type=daemon' ) );
			exit;
		}
		$upgrader = \WP2\Update\Helpers\UpgraderFactory::create( 'daemon' );
		$result = $upgrader->install( $package_data, $version, $zip_url );
		if ( is_wp_error( $result ) ) {
			Log::add( 'Install failed: ' . $result->get_error_message(), 'error', 'daemon-update' );
			wp_safe_redirect( admin_url( 'admin.php?page=wp2-update-dashboard&error=install&type=daemon' ) );
			exit;
		}
		// Only show success if install actually succeeded and something was installed
		if ( $result === true ) {
			Log::add( "Daemon '{$daemon_file}' updated to {$version}.", 'success', 'daemon-update' );
			wp_safe_redirect( admin_url( 'admin.php?page=wp2-update-dashboard&success=installed&version=' . urlencode( $version ) . '&type=daemon' ) );
			exit;
		} else {
			Log::add( 'Install failed: Unknown error, no changes made.', 'error', 'daemon-update' );
			wp_safe_redirect( admin_url( 'admin.php?page=wp2-update-dashboard&error=install&type=daemon' ) );
			exit;
		}
	}

	public function display_releases(array $daemon_data) {
		$releases = \WP2\Update\Helpers\Github::get_releases($daemon_data['repo']);
		?>
		<h2><?php esc_html_e('Available Versions', 'wp2-update'); ?></h2>
		<?php
		if (empty($releases)) {
			echo '<p>' . esc_html__('No releases found.', 'wp2-update') . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Version', 'wp2-update'); ?></th>
					<th><?php esc_html_e('Release Date', 'wp2-update'); ?></th>
					<th><?php esc_html_e('Download Link', 'wp2-update'); ?></th>
					<th><?php esc_html_e('Action', 'wp2-update'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$current_version = $daemon_data['version'] ?? '';
				foreach ($releases as $release) :
					$zip_url = '';
					$has_zip = false;
					if (!empty($release->assets)) {
						foreach ($release->assets as $asset) {
							if (isset($asset->content_type) && 'application/zip' === $asset->content_type) {
								$zip_url = $asset->browser_download_url ?? $asset->url ?? '';
								$has_zip = true;
								break;
							}
						}
					}
					$release_version = preg_replace('/^v/i', '', $release->tag_name);
					$current_version_norm = preg_replace('/^v/i', '', $current_version);
					$is_current = version_compare($release_version, $current_version_norm, '==');
					$action_label = $is_current ? __('Reinstall', 'wp2-update') : (version_compare($release_version, $current_version_norm, '<') ? __('Rollback', 'wp2-update') : __('Upgrade', 'wp2-update'));
					?>
					<tr>
						<td>
							<strong><?php echo esc_html($release->tag_name); ?></strong>
							<?php if (!empty($release->prerelease)) : ?>
								<span style="color:#d63638;">(Pre-release)</span>
								<?php if (!$has_zip) : ?>
									<div style="color:#d63638;font-size:0.95em;">⚠️ <?php esc_html_e('No ZIP asset for this pre-release.', 'wp2-update'); ?></div>
								<?php endif; ?>
							<?php endif; ?>
							<?php if (!empty($release->body)) : ?>
								<button class="button button-small wp2-view-details-button" style="margin-top:4px;">View Details</button>
								<div class="wp2-release-notes" style="display:none;">
									<?php echo wpautop( esc_html($release->body) ); ?>
								</div>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html(date('Y-m-d', strtotime($release->published_at))); ?></td>
						<td>
							<?php if ($zip_url) : ?>
								<a href="<?php echo esc_url($zip_url); ?>" target="_blank">ZIP</a>
							<?php else : ?>
								<span style="color:#d63638;">—</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ($zip_url) : ?>
								<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
									<input type="hidden" name="action" value="wp2-daemon-install">
									<input type="hidden" name="daemon" value="<?php echo esc_attr($daemon_data['file']); ?>">
									<input type="hidden" name="version" value="<?php echo esc_attr($release->tag_name); ?>">
									<?php wp_nonce_field('wp2-daemon-install-' . $daemon_data['file'] . '-' . $release->tag_name); ?>
									<button class="button" type="submit"><?php echo esc_html($action_label); ?></button>
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
}
