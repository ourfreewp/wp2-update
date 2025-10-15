<?php

namespace WP2\Update\Admin;

use WP2\Update\REST\Controllers\HealthController;
use WP2\Update\Utils\Logger;

/**
 * Handles rendering the admin screen for the WP2 Update plugin.
 * Renders the initial HTML structure and the SPA shell.
 */
final class Screens {
	private HealthController $healthController;
	private Data             $data;
	private                  $container;

	public function __construct( HealthController $healthController, Data $data, $container ) {
		$this->healthController = $healthController;
		$this->data             = $data;
		$this->container        = $container;
	}

	/**
	 * Renders the main admin page, which acts as the root for the SPA.
	 */
	public function render(): void {
		$activeTab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'dashboard';
		?>
		<div id="wp2-update-app" class="wp2-wrap">

			<div class="wp2-header d-flex flex-column flex-md-row align-items-md-center justify-content-md-between mb-4">
				<div>
					<h1 class="wp2-main-title"><?php esc_html_e( 'WP2 Update', \WP2\Update\Config::TEXT_DOMAIN ); ?></h1>
					<p><?php esc_html_e( 'Manage your GitHub-hosted plugins and themes with clarity, confidence, and control.', \WP2\Update\Config::TEXT_DOMAIN ); ?>
					</p>
				</div>
				<div class="d-flex gap-2 mt-2 mt-md-0">
					<button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createPackageModal">
						<i class="bi bi-plus-lg me-1"></i> Create Package
					</button>
					<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAppModal">
						<i class="bi bi-github me-1"></i> Add GitHub App
					</button>
					<button id="sync-all-btn" class="btn btn-outline-secondary">
						<i class="bi bi-arrow-repeat me-1"></i> Sync All
					</button>
				</div>
			</div>

			<ul class="nav nav-tabs" role="tablist">
				<li class="nav-item" role="presentation">
					<a class="nav-link <?php echo 'dashboard' === $activeTab ? 'active' : ''; ?>"
						href="<?php echo esc_url( add_query_arg( 'tab', 'dashboard' ) ); ?>" role="tab"
						aria-controls="dashboard-panel"
						aria-selected="<?php echo 'dashboard' === $activeTab ? 'true' : 'false'; ?>">
						<?php esc_html_e( 'Dashboard', \WP2\Update\Config::TEXT_DOMAIN ); ?>
					</a>
				</li>
				<li class="nav-item" role="presentation">
					<a class="nav-link <?php echo 'packages' === $activeTab ? 'active' : ''; ?>"
						href="<?php echo esc_url( add_query_arg( 'tab', 'packages' ) ); ?>" role="tab"
						aria-controls="packages-panel"
						aria-selected="<?php echo 'packages' === $activeTab ? 'true' : 'false'; ?>">
						<?php esc_html_e( 'Packages', \WP2\Update\Config::TEXT_DOMAIN ); ?>
					</a>
				</li>
				<li class="nav-item" role="presentation">
					<a class="nav-link <?php echo 'apps' === $activeTab ? 'active' : ''; ?>"
						href="<?php echo esc_url( add_query_arg( 'tab', 'apps' ) ); ?>" role="tab" aria-controls="apps-panel"
						aria-selected="<?php echo 'apps' === $activeTab ? 'true' : 'false'; ?>">
						<?php esc_html_e( 'Apps', \WP2\Update\Config::TEXT_DOMAIN ); ?>
					</a>
				</li>
				<li class="nav-item" role="presentation">
					<a class="nav-link <?php echo 'health' === $activeTab ? 'active' : ''; ?>"
						href="<?php echo esc_url( add_query_arg( 'tab', 'health' ) ); ?>" role="tab"
						aria-controls="health-panel" aria-selected="<?php echo 'health' === $activeTab ? 'true' : 'false'; ?>">
						<?php esc_html_e( 'Health', \WP2\Update\Config::TEXT_DOMAIN ); ?>
					</a>
				</li>
			</ul>

			<div class="tab-content">
				<?php $this->render_tab_panel( $activeTab ); ?>
			</div>

			<?php $this->render_all_modals(); ?>
		</div>
		<?php
	}

	/**
	 * Renders the content for the active tab.
	 * @param string $activeTab The currently active tab.
	 */
	private function render_tab_panel( string $activeTab ): void {
		?>
		<div id="<?php echo esc_attr( $activeTab ); ?>-panel" class="tab-pane fade show active" role="tabpanel">
			<?php
			switch ( $activeTab ) {
				case 'dashboard':
					include __DIR__ . '/Views/dashboard.php';
					break;
				case 'packages':
					$packages = $this->data->get_state()['packages']['all'] ?? [];
					include __DIR__ . '/Views/packages.php';
					break;
				case 'apps':
					$appService = $this->data->get_app_service();
					include __DIR__ . '/Views/apps.php';
					break;
				case 'health':
					include __DIR__ . '/Views/health.php';
					break;
				default:
					echo '<p>' . esc_html__( 'Invalid tab.', \WP2\Update\Config::TEXT_DOMAIN ) . '</p>';
			}
			?>
		</div>
		<?php
	}

	/**
	 * Renders individual modal containers for each modal type.
	 * The actual content is populated by the JavaScript components.
	 */
	private function render_all_modals(): void {
		$modals = [
			'createPackageModal' => __( 'Create Package', 'wp2-update' ),
			'addAppModal' => __( 'Add GitHub App', 'wp2-update' ),
			'rollbackModal' => __( 'Rollback Package', 'wp2-update' ),
			'assignAppModal' => __( 'Assign App', 'wp2-update' ),
			'packageDetailsModal' => __( 'Package Details', 'wp2-update' ),
		];

		foreach ( $modals as $id => $title ) {
			?>
			<div class="modal fade" id="<?php echo esc_attr( $id ); ?>" tabindex="-1" aria-labelledby="<?php echo esc_attr( $id ); ?>Label" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered modal-lg">
					<div class="modal-content">
						<!-- Content dynamically injected by JavaScript -->
					</div>
				</div>
			</div>
			<?php
		}
	}
}
