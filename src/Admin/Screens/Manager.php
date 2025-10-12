<?php

namespace WP2\Update\Admin\Screens;

use WP2\Update\Admin\DashboardData;
use WP2\Update\Utils\Logger;
use WP2\Update\REST\Controllers\HealthController;

/**
 * Handles rendering the admin screen for the WP2 Update plugin.
 * Renders the initial HTML structure for the dashboard.
 */
final class Manager {

	private HealthController $healthController;

	public function __construct( HealthController $healthController ) {
		$this->healthController = $healthController;
	}

	public function render(): void {
		$package_data = DashboardData::get_packages();
		$apps         = DashboardData::get_apps();
		$active_tab   = $_GET['tab'] ?? 'packages';

		// Log the packages data for debugging
		Logger::log('DEBUG', 'Packages for rendering: ' . print_r($package_data, true));

		// Render navigation tabs
		?>
		<div id="wp2-update-app" class="wp2-wrap">
			<h1 class="wp2-main-title"><?php esc_html_e( 'WP2 Update', 'wp2-update' ); ?></h1>
			<div class="wp2-dashboard__actions">
				<button type="button" id="wp2-add-app-button" class="page-title-action">
					<?php esc_html_e( 'Add GitHub App', 'wp2-update' ); ?>
				</button>
			</div>

			<nav class="nav-tab-wrapper wp2-tabs">

				<a href="?page=wp2-update&tab=dashboard" class="nav-tab <?php echo 'dashboard' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Dashboard', 'wp2-update' ); ?>
				</a>
				<a href="?page=wp2-update&tab=packages" class="nav-tab <?php echo 'packages' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Packages', 'wp2-update' ); ?>
				</a>
				<a href="?page=wp2-update&tab=apps" class="nav-tab <?php echo 'apps' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Apps', 'wp2-update' ); ?>
				</a>
				<a href="?page=wp2-update&tab=health" class="nav-tab <?php echo 'health' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Health', 'wp2-update' ); ?>
				</a>
			</nav>

			<div class="wp2-tab-content">

				<div id="wp2-dashboard-panel" class="wp2-tab-panel" style="display: <?php echo 'dashboard' === $active_tab ? 'block' : 'none'; ?>;">
					<?php self::render_dashboard_view(); ?>
				</div>
				<div id="wp2-packages-panel" class="wp2-tab-panel" style="display: <?php echo 'packages' === $active_tab ? 'block' : 'none'; ?>;">
					<?php self::render_packages_table( $package_data ); ?>
				</div>
				<div id="wp2-apps-panel" class="wp2-tab-panel" style="display: <?php echo 'apps' === $active_tab ? 'block' : 'none'; ?>;">
					<?php self::render_apps_table( $apps ); ?>
				</div>
				<div id="wp2-health-panel" class="wp2-tab-panel" style="display: <?php echo 'health' === $active_tab ? 'block' : 'none'; ?>;">
					<?php $this->render_health_panel(); ?>
				</div>
			</div>

			<?php self::render_modals(); ?>
		</div>
		<script type="module">
			import { handleAutoUpdateToggle } from '/assets/scripts/modules/ui/handlers/AutoUpdateHandler.js';

			document.querySelectorAll('.wp2-auto-update-toggle').forEach(toggle => {
				toggle.addEventListener('change', handleAutoUpdateToggle);
			});
		</script>
		<?php
	}

	private static function render_modals(): void {
		?>
		<div id="wp2-modal-app" class="wp2-modal-overlay" hidden>
			<div class="wp2-modal-window wp2-modal-window--wide">
				<button type="button" class="wp2-modal-close" data-wp2-close="app">×</button>
				<div class="wp2-modal-body">
					<div class="wp2-modal-header">
						<h2 id="wp2-app-details-title"><?php esc_html_e('App details', 'wp2-update'); ?></h2>
					</div>
					<div id="wp2-app-details-content" class="wp2-detail-grid"></div>
					<div id="wp2-app-managed-packages" class="wp2-managed-list"></div>
					<div class="wp2-modal-actions">
						<button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-close="app"><?php esc_html_e('Close', 'wp2-update'); ?></button>
					</div>
				</div>
			</div>
		</div>
		<div id="wp2-modal-assign" class="wp2-modal-overlay" hidden>
			<div class="wp2-modal-window">
				<button type="button" class="wp2-modal-close" data-wp2-close="assign">×</button>
				<div class="wp2-modal-body">
					<div class="wp2-modal-header">
						<h2><?php esc_html_e('Assign GitHub App', 'wp2-update'); ?></h2>
						<p id="wp2-assign-description"><?php esc_html_e('Choose a GitHub App to manage this package.', 'wp2-update'); ?></p>
					</div>
					<form id="wp2-assign-form" class="wp2-form">
						<label class="wp2-form-label" for="wp2-assign-app-select"><?php esc_html_e('Available Apps', 'wp2-update'); ?></label>
						<select id="wp2-assign-app-select" class="wp2-input"></select>
						<div class="wp2-modal-actions">
							<button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-close="assign"><?php esc_html_e('Cancel', 'wp2-update'); ?></button>
							<button type="submit" class="wp2-btn wp2-btn--primary"><?php esc_html_e('Assign App', 'wp2-update'); ?></button>
						</div>
					</form>
				</div>
			</div>
		</div>
		<div id="wp2-modal-package" class="wp2-modal-overlay" hidden>
			<div class="wp2-modal-window wp2-modal-window--wide">
				<button type="button" class="wp2-modal-close" data-wp2-close="package">×</button>
				<div class="wp2-modal-body">
					<div class="wp2-modal-header">
						<h2 id="wp2-package-details-title"><?php esc_html_e('Package details', 'wp2-update'); ?></h2>
					</div>
					<div id="wp2-package-details-content" class="wp2-detail-grid"></div>
					<div id="wp2-package-sync-log" class="wp2-sync-log" hidden></div>
					<div class="wp2-modal-actions">
						<button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-close="package"><?php esc_html_e('Close', 'wp2-update'); ?></button>
					</div>
				</div>
			</div>
		</div>
		<div id="wp2-modal-wizard" class="wp2-modal-overlay" hidden>
			<div class="wp2-modal-window wp2-modal-window--wide">
				<button type="button" class="wp2-modal-close" data-wp2-close="wizard">×</button>
				<div class="wp2-modal-body">
					<div id="wp2-wizard-step-configure" class="wp2-wizard-step">
						<header class="wp2-modal-header">
							<h2><?php esc_html_e('Add GitHub App', 'wp2-update'); ?></h2>
							<p><?php esc_html_e('Generate a manifest and connect a new GitHub App for this site.', 'wp2-update'); ?></p>
						</header>
						<form id="wp2-wizard-form" class="wp2-form">
							<div class="wp2-form-grid">
								<div class="wp2-form-field">
									<label class="wp2-form-label" for="wp2-wizard-app-name"><?php esc_html_e('App name', 'wp2-update'); ?></label>
									<input id="wp2-wizard-app-name" name="app_name" class="wp2-input" type="text" required />
								</div>
							</div>
							<div class="wp2-modal-actions">
								<button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-close="wizard"><?php esc_html_e('Cancel', 'wp2-update'); ?></button>
								<button type="submit" class="wp2-btn wp2-btn--primary"><?php esc_html_e('Generate manifest', 'wp2-update'); ?></button>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
		<div id="wp2-release-notes-modal" class="wp2-modal-overlay" hidden>
			<div class="wp2-modal-window">
				<button type="button" class="wp2-modal-close" data-wp2-close="release-notes">×</button>
				<div class="wp2-modal-body">
					<div class="wp2-modal-header">
						<h2><?php esc_html_e('Release Notes', 'wp2-update'); ?></h2>
					</div>
					<div id="wp2-release-notes-content" class="wp2-release-notes-content">
						<!-- Release notes will be dynamically loaded here -->
					</div>
					<div class="wp2-modal-actions">
						<button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-close="release-notes">
							<?php esc_html_e('Close', 'wp2-update'); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	private static function render_partial(string $template, array $data = []): void {
        $file = WP2_UPDATE_PLUGIN_DIR . "src/Admin/Views/{$template}.php";
        if (file_exists($file)) {
            extract($data);
            include $file;
        }
    }

    private static function render_packages_table( array $package_data ): void {
        self::render_partial('packages-table', ['packages' => $package_data['all'] ?? []]);
    }

    private static function render_apps_table( array $apps ): void {
        self::render_partial('apps-table', ['apps' => $apps]);
    }

	/**
	 * Generates the server-side wizard steps content.
	 */
	private static function render_wizard_steps(): void {
		?>
		<div id="wp2-wizard-step-configure" class="wp2-wizard-step">
			<header class="wp2-modal-header">
				<h2><?php esc_html_e( 'Add GitHub App', 'wp2-update' ); ?></h2>
				<p><?php esc_html_e( 'Generate a manifest and connect a new GitHub App for this site.', 'wp2-update' ); ?></p>
			</header>
			<form id="wp2-wizard-form" class="wp2-form">
				<div class="wp2-form-grid">
					<div class="wp2-form-field">
						<label class="wp2-form-label" for="wp2-wizard-app-name"><?php esc_html_e( 'App name', 'wp2-update' ); ?></label>
						<input id="wp2-wizard-app-name" name="app_name" class="wp2-input" type="text" required />
					</div>
					<div class="wp2-form-field">
						<label class="wp2-form-label" for="wp2-wizard-encryption-key"><?php esc_html_e( 'Encryption key', 'wp2-update' ); ?></label>
						<input id="wp2-wizard-encryption-key" name="encryption_key" class="wp2-input" type="password" minlength="16" required />
						<p class="wp2-field-help"><?php esc_html_e( 'Use at least 16 characters. Store this securely for future connections.', 'wp2-update' ); ?></p>
					</div>
				</div>
				<div class="wp2-form-field">
					<label class="wp2-form-label"><?php esc_html_e( 'Account type', 'wp2-update' ); ?></label>
					<div class="wp2-pill-group" id="wp2-wizard-account">
						<label class="wp2-pill">
							<input type="radio" name="account_type" value="user" checked />
							<span><?php esc_html_e( 'Personal', 'wp2-update' ); ?></span>
						</label>
						<label class="wp2-pill">
							<input type="radio" name="account_type" value="organization" />
							<span><?php esc_html_e( 'Organization', 'wp2-update' ); ?></span>
						</label>
					</div>
				</div>
				<div class="wp2-form-field" id="wp2-wizard-organization-field" hidden>
					<label class="wp2-form-label" for="wp2-wizard-organization"><?php esc_html_e( 'Organization slug', 'wp2-update' ); ?></label>
					<input id="wp2-wizard-organization" name="organization" class="wp2-input" type="text" placeholder="your-org-name" />
				</div>
				<div class="wp2-modal-actions">
					<button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-close="wizard"><?php esc_html_e( 'Cancel', 'wp2-update' ); ?></button>
					<button type="submit" class="wp2-btn wp2-btn--primary"><?php esc_html_e( 'Generate manifest', 'wp2-update' ); ?></button>
				</div>
			</form>
		</div>
		<div id="wp2-wizard-step-manifest" class="wp2-wizard-step" hidden>
			<header class="wp2-modal-header">
				<h2><?php esc_html_e( 'Finish GitHub setup', 'wp2-update' ); ?></h2>
				<p><?php esc_html_e( 'Paste this manifest into GitHub to create the App, then install it on your repositories.', 'wp2-update' ); ?></p>
			</header>
			<div class="wp2-form-field">
				<label class="wp2-form-label" for="wp2-wizard-manifest"><?php esc_html_e( 'GitHub App manifest', 'wp2-update' ); ?></label>
				<textarea id="wp2-wizard-manifest" class="wp2-input wp2-input--code" rows="12" readonly></textarea>
				<div class="wp2-manifest-actions">
					<button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-action="copy-manifest"><?php esc_html_e( 'Copy manifest', 'wp2-update' ); ?></button>
					<button type="button" class="wp2-btn wp2-btn--primary-outline" data-wp2-action="open-github"><?php esc_html_e( 'Open GitHub', 'wp2-update' ); ?></button>
				</div>
			</div>
			<p class="wp2-manifest-note"><?php esc_html_e( 'GitHub will redirect you back here when installation completes. You can always retry the connection from the Settings tab.', 'wp2-update' ); ?></p>
			<div class="wp2-modal-actions">
				<button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-close="wizard"><?php esc_html_e( 'Close', 'wp2-update' ); ?></button>
				<button type="button" class="wp2-btn wp2-btn--primary" data-wp2-action="wizard-finished"><?php esc_html_e( 'I’ve installed the app', 'wp2-update' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the modal for assigning GitHub Apps.
	 */
	private static function render_assign_modal(): void {
		?>
		<div id="wp2-modal-assign" class="wp2-modal" style="display: none;">
			<div class="wp2-modal-content">
				<span data-wp2-close class="wp2-modal-close">&times;</span>
				<div class="wp2-modal-header">
					<h2><?php esc_html_e( 'Assign GitHub App', 'wp2-update' ); ?></h2>
					<p id="wp2-assign-description">Choose a GitHub App to manage this package.</p>
				</div>
				<form id="wp2-assign-form" class="wp2-form">
					<label class="wp2-form-label" for="wp2-assign-app-select">Available Apps</label>
					<select id="wp2-assign-app-select" class="wp2-input"></select>
					<div class="wp2-modal-actions">
						<button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-close="assign">Cancel</button>
						<button type="submit" class="wp2-btn wp2-btn--primary">Assign App</button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the modal for package details.
	 */
	private static function render_package_details_modal(): void {
		?>
		<div id="wp2-modal-package" class="wp2-modal" style="display: none;">
			<div class="wp2-modal-content">
				<span class="wp2-modal-close">&times;</span>
				<div class="wp2-modal-header">
					<h2 id="wp2-package-details-title">Package details</h2>
				</div>
				<div id="wp2-package-details-content" class="wp2-detail-grid"></div>
				<div id="wp2-package-sync-log" class="wp2-sync-log" hidden></div>
				<div class="wp2-modal-actions">
					<button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-close="package">Close</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the modal for app details.
	 */
	private static function render_app_details_modal(): void {
		?>
		<div id="wp2-modal-app" class="wp2-modal" style="display: none;">
			<div class="wp2-modal-content">
				<span class="wp2-modal-close">&times;</span>
				<div class="wp2-modal-header">
					<h2 id="wp2-app-details-title">App details</h2>
				</div>
				<div id="wp2-app-details-content" class="wp2-detail-grid"></div>
				<div id="wp2-app-managed-packages" class="wp2-managed-list"></div>
				<div class="wp2-modal-actions">
					<button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-close="app">Close</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the debug panel content.
	 */
	private static function render_debug_panel(): void {
		?>
		<div id="wp2-debug-view-container"></div>
		<script type="module">
			import { DebugView } from '/assets/scripts/modules/ui/views/DebugView.js';
			const container = document.getElementById('wp2-debug-view-container');
			DebugView().then(view => container.appendChild(view));
		</script>
		<?php
	}

	private function render_health_panel(): void {
		$health_data = $this->healthController->get_health_status( new \WP_REST_Request() );
		self::render_partial( 'health-panel', [ 'health_checks' => $health_data->get_data() ] );
	}

	private static function render_dashboard_view(): void {
		$dashboardData = DashboardData::get_dashboard_data();
		?>
		<div class="wp2-dashboard-view">
			<h2><?php esc_html_e('Dashboard', 'wp2-update'); ?></h2>
			<ul>
				<?php foreach ($dashboardData as $app): ?>
					<li>
						<strong><?php echo esc_html($app['name']); ?></strong>
						<p><?php esc_html_e('Status:', 'wp2-update'); ?> <?php echo esc_html($app['status']); ?></p>
						<p><?php esc_html_e('Managed Repositories:', 'wp2-update'); ?> <?php echo count($app['managed_repositories']); ?></p>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}
}
