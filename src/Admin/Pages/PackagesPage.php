<?php
namespace WP2\Update\Admin\Pages;

use WP2\Update\Core\Connection\Init as Connection;
use WP2\Update\Utils\SharedUtils;
use WP2\Update\Core\API\GitHubApp\Init as GitHubApp;
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
	private $package_finder;

	/**
	 * Constructor.
	 */
	public function __construct( Connection $connection, SharedUtils $utils, GitHubApp $github_app, $package_finder, PackageHistoryPage $history_tab, PackageStatusPage $status_tab, PackageEventsPage $log_tab ) {
        $this->connection = $connection;
        $this->utils      = $utils;
        $this->github_app = $github_app;
        $this->package_finder = $package_finder;
        $this->history_tab = $history_tab;
        $this->status_tab = $status_tab;
        $this->log_tab = $log_tab;
    }

	/**
	 * Renders the packages table view with a refresh button.
	 */
	public function render() {
		$this->print_notices();
		$current_package_key = isset($_GET['package']) ? sanitize_text_field($_GET['package']) : null;

		?>
		<div class="wrap wp2-update-page">
			<div class="wp2-update-card">
				<div class="wp2-update-header">
					<h1>
						<?php echo $current_package_key
							? esc_html__( 'Package Details', 'wp2-update' )
							: esc_html__( 'Managed Packages', 'wp2-update' ); ?>
					</h1>
					<?php if ( ! $current_package_key ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'force-check', '1', admin_url( 'admin.php?page=wp2-update-packages' ) ) ); ?>" class="page-title-action">
							<?php esc_html_e( 'Refresh Packages', 'wp2-update' ); ?>
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
        $list_table = new \WP2\Update\Admin\Tables\PackagesListTable($this->connection, $this->utils);
        $list_table->prepare_items();
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="wp2_bulk_action">
            <input type="hidden" name="packages[]" value=""> <!-- Ensure empty value is submitted if no packages are selected -->
            <?php wp_nonce_field( 'wp2_bulk_action_packages', 'wp2_bulk_action_nonce' ); ?>
            <?php $list_table->display(); ?>
        </form>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const installButtons = document.querySelectorAll('.install-button');

                installButtons.forEach(button => {
                    button.addEventListener('click', function () {
                        button.disabled = true;
                        const spinner = document.createElement('span');
                        spinner.className = 'spinner is-active';
                        button.appendChild(spinner);
                    });
                });
            });
        </script>
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
        $cache_key = 'wp2_merged_packages_data';
        $cached_data = get_transient($cache_key);
        if (is_array($cached_data)) {
            return $cached_data;
        }

        // Refactor to use cached data from PackageFinder
        $themes  = $this->package_finder->get_managed_themes();
        $plugins = $this->package_finder->get_managed_plugins();

        $theme_updates  = get_site_transient( 'update_themes' );
        $plugin_updates = get_site_transient( 'update_plugins' );

        $all_packages = [];

        foreach ( $themes as $slug => $data ) {
            $installed_version = $data['version'];
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
            $installed_version = $data['version'];
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

        set_transient($cache_key, $all_packages, 1 * HOUR_IN_SECONDS);

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
			'installed' => sprintf(__('Package version %s installed successfully!', 'wp2-update'), esc_html($_GET['installed'] ?? '')),
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
