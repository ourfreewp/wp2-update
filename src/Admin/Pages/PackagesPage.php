<?php
namespace WP2\Update\Admin\Pages;

use WP2\Update\Core\Connection\Init as Connection;
use WP2\Update\Core\Utils\SharedUtils;
use WP2\Update\Core\GitHubApp\Init as GitHubApp;
use WP2\Update\Admin\Pages\PackageHistoryPage;
use WP2\Update\Admin\Pages\PackageEventsPage;
use WP2\Update\Admin\Pages\PackageStatusPage;

/**
 * Renders the main "Packages" page with a table of all managed items.
 *
 * This class has been refactored to be the single source of truth for
 * rendering both the list of packages and the detailed view for a single package.
 * The old, redundant `PackageDetailPage.php` has been removed.
 */
class PackagesPage {
	private $connection;
	private $utils;
	private $history_tab;
	private $status_tab;
	private $log_tab;
	private $github_app;

	/**
	 * Constructor.
	 */
	public function __construct( Connection $connection, SharedUtils $utils, GitHubApp $github_app ) {
		$this->connection = $connection;
		$this->utils      = $utils;
		$this->github_app = $github_app;
		$this->history_tab = new PackageHistoryPage( $connection, $utils );
		$this->status_tab = new PackageStatusPage( $connection, $github_app, $utils );
		$this->log_tab = new PackageEventsPage();
	}

	/**
	 * Renders the packages table view.
	 */
	public function render() {
		$this->print_notices();
		$current_package_key = $_GET['package'] ?? null;

		?>
		<div class="wrap wp2-update-page">
			<div class="wp2-update-card">
				<div class="wp2-update-header">
					<h1>
						<?php echo $current_package_key
							? esc_html__( 'Package Details', 'wp2-update' )
							: esc_html__( 'Managed Packages', 'wp2-update' ); ?>
					</h1>
					<?php if ( $current_package_key ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp2-update-packages' ) ); ?>" class="page-title-action">
							<?php esc_html_e( 'Back to All Packages', 'wp2-update' ); ?>
						</a>
					<?php endif; ?>
				</div>

				<div class="wp2-tab-content" style="padding: 1.5rem;">
					<?php if ( $current_package_key ) {
						$this->render_details_view( $current_package_key );
					} else {
						$this->render_list_view();
					} ?>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_list_view() {
		$all_packages = $this->get_all_packages_with_data();
		?>
		<table class="wp2-data-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Package Name', 'wp2-update' ); ?></th>
					<th><?php esc_html_e( 'Type', 'wp2-update' ); ?></th>
					<th><?php esc_html_e( 'Installed Version', 'wp2-update' ); ?></th>
					<th><?php esc_html_e( 'Latest Version', 'wp2-update' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp2-update' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wp2-update' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $all_packages ) ) : ?>
					<tr>
						<td colspan="6">
							<?php esc_html_e( 'No managed packages found. Add an "Update URI" to a theme or plugin header to begin.', 'wp2-update' ); ?>
						</td>
					</tr>
				<?php else :
					foreach ( $all_packages as $pkg ) : ?>
						<tr>
							<td data-label="Name"><strong><?php echo esc_html( $pkg['name'] ); ?></strong></td>
							<td data-label="Type"><?php echo esc_html( ucfirst( $pkg['type'] ) ); ?></td>
							<td data-label="Installed"><?php echo esc_html( $pkg['installed_version'] ); ?></td>
							<td data-label="Latest"><?php echo esc_html( $pkg['latest_version'] ?? 'N/A' ); ?></td>
							<td data-label="Status">
								<span
									class="wp2-status-item__value <?php echo $pkg['update_available'] ? 'status-success' : 'status-info'; ?>">
									<?php echo $pkg['update_available'] ? esc_html__( 'Update Available', 'wp2-update' ) : esc_html__( 'Up to Date', 'wp2-update' ); ?>
								</span>
							</td>
							<td data-label="Actions" class="cell-action">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp2-update-packages&package=' . urlencode( $pkg['key'] ) ) ); ?>"
									class="wp2-button wp2-button--small wp2-button--secondary">
									<?php esc_html_e( 'View Details', 'wp2-update' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; endif; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_details_view( $package_key ) {
		list($type, $slug) = explode(':', $package_key, 2);

		if ( ! $type || ! $slug ) {
			echo '<p>' . esc_html__( 'Invalid package specified.', 'wp2-update' ) . '</p>';
			return;
		}

		?>
		<div class="wp2-tabs-container">
			<ul id="wp2-tabs-list" data-tabs>
				<li><a data-tabby-default href="#status"><?php esc_html_e( 'Status', 'wp2-update' ); ?></a></li>
				<li><a href="#history"><?php esc_html_e( 'Version History', 'wp2-update' ); ?></a></li>
				<li><a href="#log"><?php esc_html_e( 'Event Log', 'wp2-update' ); ?></a></li>
			</ul>
		</div>

		<div class="wp2-tab-content">
			<div id="status">
				<?php $this->status_tab->render( $type, $slug ); ?>
			</div>
			<div id="history">
				<?php $this->history_tab->render( $type, $slug ); ?>
			</div>
			<div id="log">
				<?php $this->log_tab->render(); ?>
			</div>
		</div>
		<?php
	}
    
	/**
	 * Fetches and merges all managed themes and plugins into a single array.
	 */
	private function get_all_packages_with_data(): array {
		$themes  = $this->connection->get_managed_themes();
		$plugins = $this->connection->get_managed_plugins();

		$theme_updates  = get_site_transient( 'update_themes' );
		$plugin_updates = get_site_transient( 'update_plugins' );
		
		// Get all plugin data at once
		if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
		$all_plugins_data = get_plugins();

		$all_packages = [];

		foreach ( $themes as $slug => $data ) {
			$installed_version = wp_get_theme( $slug )->get( 'Version' );
			$latest_version    = $theme_updates->response[ $slug ]['new_version'] ?? null;
			$all_packages[]    = [
				'key'               => 'theme:' . $slug,
				'slug'              => $slug,
				'repo'              => $data['repo'],
				'name'              => $data['name'],
				'type'              => 'theme',
				'installed_version' => $installed_version,
				'latest_version'    => $latest_version,
				'update_available'  => ! empty( $latest_version ) && version_compare( $latest_version, $installed_version, '>' ),
			];
		}

		foreach ( $plugins as $slug => $data ) {
			// Use the data from get_plugins() instead of reading the file again
			$installed_version = $all_plugins_data[$slug]['Version'] ?? 'N/A';
			$latest_version    = $plugin_updates->response[ $slug ]->new_version ?? null;
			$all_packages[]    = [
				'key'               => 'plugin:' . $slug,
				'slug'              => $slug,
				'repo'              => $data['repo'],
				'name'              => $data['name'],
				'type'              => 'plugin',
				'installed_version' => $installed_version,
				'latest_version'    => $latest_version,
				'update_available'  => ! empty( $latest_version ) && version_compare( $latest_version, $installed_version, '>' ),
			];
		}

		return $all_packages;
	}

	/**
	 * Prints admin notices for the packages page.
	 */
	private function print_notices() {
		$notices = [
			'update_success' => esc_html__( 'Package updated successfully.', 'wp2-update' ),
			'update_failed'  => esc_html__( 'Package update failed. Please try again.', 'wp2-update' ),
			'invalid_package' => esc_html__( 'The selected package is invalid.', 'wp2-update' ),
			'installed' => sprintf(__('Package version %s installed successfully!', 'wp2-update'), sanitize_text_field($_GET['installed'] ?? '')),
		];

        $get_notice = sanitize_text_field($_GET['notice'] ?? '');
        $is_success = in_array($get_notice, ['update_success', 'installed']);

        if (isset($_GET['installed']) && isset($_GET['wp2_notice_nonce'])) {
            $slug = sanitize_text_field($_GET['slug'] ?? '');
            if (wp_verify_nonce($_GET['wp2_notice_nonce'], 'wp2_install_success_' . $slug)) {
                $get_notice = 'installed';
            }
        }

        if (isset($notices[$get_notice])) {
            $class = $is_success ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($notices[$get_notice]) . '</p></div>';
        }

        // Display install failure notice from transient
        if ($error_message = get_transient('wp2_update_error_notice')) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_message) . '</p></div>';
            delete_transient('wp2_update_error_notice');
        }
	}
}
