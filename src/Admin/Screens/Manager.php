<?php

namespace WP2\Update\Admin\Screens;

/**
 * Handles rendering the admin screen for the WP2 Update plugin.
 */
final class Manager {

	/**
	 * Renders the main admin page for the plugin.
	 * This acts as the root for the single-page application.
	 */
	public static function render(): void {
		?>
		<div class="container">
			<h1><?php esc_html_e( 'GitHub Package Updater Wizard', 'wp2-update' ); ?></h1>
			<p class="main-subtitle">Static Inspection View: All Workflow Steps Displayed.</p>

			<?php
				self::render_configure_manifest();
				self::render_connecting_to_github();
				self::render_managing();
				self::render_disconnected();
				self::render_modal(
					'disconnect-modal',
					'Disconnect Confirmation Modal Example',
					'Confirm Disconnection',
					'Are you sure you want to disconnect? This will remove all package updates.',
					'Cancel',
					'Confirm Disconnect',
					'button-danger'
				);
			?>
		</div>
		<?php
	}

	private static function render_configure_manifest(): void {
		?>
		<!-- 1. Configure Manifest View (Step 1) -->
		<div id="configure-manifest" class="workflow-step">
			<h2 class="text-center">1. Configure GitHub App</h2>
			<form id="manifest-config-form" class="form-container">
				<div class="form-row">
					<label for="app-name">App Name</label>
					<input type="text" id="app-name" name="app-name" required value="My Site Updater" disabled>
				</div>
				<div class="form-row">
					<label for="app-type">Account Type</label>
					<select id="app-type" name="app-type" disabled>
						<option value="user">User</option>
						<option value="organization">Organization</option>
					</select>
				</div>
				<div class="form-row" id="org-name-row" style="display: none;">
					<label for="organization">Organization Name (Username)</label>
					<input type="text" id="organization" name="organization" placeholder="e.g., 'my-company-llc'" disabled>
				</div>
				<div class="form-footer">
					<button class="button button-primary" data-action="submit-manifest-config" disabled>
						<?php esc_html_e( 'Save and Continue to GitHub', 'wp2-update' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
	}

	private static function render_connecting_to_github(): void {
		?>
		<div id="connecting-to-github" class="workflow-step text-center">
			<h2 class="text-center">2. Connecting/Redirecting State</h2>
			<p style="margin-bottom: 1.5rem;">Simulating redirect to GitHub for App installation.</p>
			<div class="spinner large-spinner"></div>
			<p style="margin-bottom: 1.5rem;">Manual actions below simulate success or cancellation.</p>
			<div class="form-footer flex flex-col items-center gap-3">
				<button class="button button-primary" data-action="simulate-successful-connection" disabled>
					<?php esc_html_e( 'Simulate Successful Connection', 'wp2-update' ); ?>
				</button>
				<button class="button button-secondary" data-action="cancel-connection" disabled>
					<?php esc_html_e( 'Cancel and Go Back', 'wp2-update' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	private static function render_managing(): void {
		?>
		<div id="managing" class="workflow-step">
			<h2 style="text-align: center; margin-bottom: 2rem;">3. Manage Packages Dashboard</h2>

			<div style="margin-bottom: 2rem;">
				<h3></h3>
				<div class="view-header">
					<div>
						<p id="wp2-last-sync" style="text-align: left; font-size: 0.875rem; margin-bottom: 0;">Last Synced: 1:53:05 PM</p>
					</div>
					<div class="view-header-actions">
						<button class="button button-secondary" data-action="sync-packages">
							<span id="sync-icon">Sync Packages</span>
						</button>
						<button class="button button-danger" data-action="disconnect">
							<?php esc_html_e( 'Disconnect', 'wp2-update' ); ?>
						</button>
					</div>
				</div>

				<div class="table-container">
					<table>
						<thead>
							<tr>
								<th><?php esc_html_e( 'Package', 'wp2-update' ); ?></th>
								<th><?php esc_html_e( 'Installed', 'wp2-update' ); ?></th>
								<th><?php esc_html_e( 'Available Version', 'wp2-update' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'wp2-update' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td>
									<div style="font-weight: 500; color: var(--text-color-primary);">My Awesome Plugin</div>
									<div style="color: var(--text-color-secondary);">my-org/my-awesome-plugin</div>
								</td>
								<td>v1.0.0</td>
								<td>
									<select class="release-dropdown" data-package-repo="my-org/my-awesome-plugin">
										<option value="v1.1.0" selected>Version 1.1.0</option>
										<option value="v1.0.1">Version 1.0.1 (patch)</option>
										<option value="v1.0.0">Version 1.0.0</option>
									</select>
								</td>
								<td>
									<button class="button button-primary" data-action="update-package" data-package-repo="my-org/my-awesome-plugin">Update</button>
								</td>
							</tr>
							<tr class="updating-mock">
								<td>
									<div style="font-weight: 500; color: var(--text-color-primary);">Another Cool Theme</div>
									<div style="color: var(--text-color-secondary);">another-dev/another-cool-theme</div>
								</td>
								<td>v2.1.0</td>
								<td>
									<select class="release-dropdown">
										<option value="v2.2.0-beta" selected>Version 2.2.0 Beta (Pre-release)</option>
										<option value="v2.1.0">Version 2.1.0</option>
									</select>
								</td>
								<td>
									<button class="button button-primary">Update</button>
								</td>
							</tr>
							<tr>
								<td>
									<div style="font-weight: 500; color: var(--text-color-primary);">Uninstalled Utility</div>
									<div style="color: var(--text-color-secondary);">utility-co/uninstalled-utility</div>
								</td>
								<td><?php esc_html_e( 'Not Installed', 'wp2-update' ); ?></td>
								<td>
									<select class="release-dropdown" data-package-repo="utility-co/uninstalled-utility">
										<option value="v1.0.0" selected>Initial Release</option>
									</select>
								</td>
								<td>
									<button class="button button-primary" data-action="install-package" data-package-repo="utility-co/uninstalled-utility">Install</button>
								</td>
							</tr>
							<tr>
								<td>
									<div style="font-weight: 500; color: var(--text-color-primary);">Errored Package (No Releases)</div>
									<div style="color: var(--text-color-secondary);">buggy/errored-package</div>
								</td>
								<td>v0.5.0</td>
								<td>
									<span style="color: var(--text-color-secondary);">No releases found</span>
								</td>
								<td>
									<span style="color: #b91c1c;">Failed to fetch releases from GitHub.</span>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<hr style="margin-bottom: 1.5rem; border-style: dotted;">

			<div style="margin-bottom: 2rem;">
				<h3></h3>
				<div class="table-container">
					<table>
						<thead>
							<tr>
								<th><?php esc_html_e( 'Package', 'wp2-update' ); ?></th>
								<th><?php esc_html_e( 'Installed', 'wp2-update' ); ?></th>
								<th><?php esc_html_e( 'Available Version', 'wp2-update' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'wp2-update' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr class="updating">
								<td>
									<div style="font-weight: 500; color: var(--text-color-primary);">Another Cool Theme</div>
									<div style="color: var(--text-color-secondary);">another-dev/another-cool-theme</div>
								</td>
								<td>v2.1.0</td>
								<td>
									<select class="release-dropdown" disabled>
										<option value="v2.2.0-beta">Version 2.2.0 Beta (Pre-release)</option>
										<option value="v2.1.0">Version 2.1.0</option>
									</select>
								</td>
								<td>
									<button class="button button-primary" disabled>
										<span class="spinner"></span><?php esc_html_e( 'Updating...', 'wp2-update' ); ?>
									</button>
								</td>
							</tr>
							<!-- Other packages are disabled during this time -->
							<tr>
								<td><?php esc_html_e( 'Other Package', 'wp2-update' ); ?></td>
								<td>...</td>
								<td>...</td>
								<td><button class="button button-primary" disabled><?php esc_html_e( 'Update', 'wp2-update' ); ?></button></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<hr style="margin-bottom: 1.5rem; border-style: dotted;">

			<div>
				<h3></h3>
				<div class="table-container">
					<table>
						<thead>
							<tr>
								<th><?php esc_html_e( 'Package', 'wp2-update' ); ?></th>
								<th><?php esc_html_e( 'Installed', 'wp2-update' ); ?></th>
								<th><?php esc_html_e( 'Available Version', 'wp2-update' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'wp2-update' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr><td colspan="4" style="padding: 1rem; text-align: center; color: var(--wp2-color-error);">
								<?php esc_html_e( 'Failed to sync: GitHub API rate limit exceeded.', 'wp2-update' ); ?>
								<button class="text-blue-600 underline bg-transparent border-none cursor-pointer hover:text-blue-800" style="margin-left: 0.5rem; color: var(--brand-color); text-decoration: underline; background: none; border: none; cursor: pointer;">
									<?php esc_html_e( 'Retry', 'wp2-update' ); ?>
								</button>
							</td></tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	private static function render_disconnected(): void {
		?>
		<div id="disconnected" class="workflow-step text-center">
			<h2 class="text-center">4. Disconnected State (Final)</h2>
			<p style="margin-bottom: 1rem;">You have successfully disconnected from GitHub.</p>
			<div class="text-center">
				<button class="button button-primary">Start Over</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders a reusable modal dialog.
	 *
	 * @param string $id        Modal container ID.
	 * @param string $title     Modal title.
	 * @param string $heading   Modal heading.
	 * @param string $message   Modal message.
	 * @param string $cancel    Cancel button label.
	 * @param string $confirm   Confirm button label.
	 * @param string $confirm_class Additional class for confirm button.
	 */
	private static function render_modal(
		string $id = 'disconnect-modal',
		string $title = 'Disconnect Confirmation Modal Example',
		string $heading = 'Confirm Disconnection',
		string $message = 'Are you sure you want to disconnect? This will remove all package updates.',
		string $cancel = 'Cancel',
		string $confirm = 'Confirm Disconnect',
		string $confirm_class = 'button-danger'
	): void {
		?>
		<div id="<?php echo esc_attr( $id ); ?>">
			<h2 class="text-center" style="margin-bottom: 1rem;"><?php echo esc_html( $title ); ?></h2>
			<div class="modal-content">
				<h3 class="font-semibold text-lg text-center" style="margin-bottom: 0.5rem; color: var(--text-color-primary);">
					<?php echo esc_html( $heading ); ?>
				</h3>
				<p class="modal-message"><?php echo esc_html( $message ); ?></p>
				<div class="modal-actions">
					<button class="button button-secondary" data-action="cancel-disconnect">Cancel</button>
					<button class="button <?php echo esc_attr( $confirm_class ); ?>" data-action="confirm-disconnect">Confirm Disconnect</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the page for handling the GitHub App installation callback.
	 * This is a separate endpoint from the main manager screen.
	 */
	public static function render_github_callback(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp2-update' ) );
		}

		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';

		if ( empty( $state ) || ! wp_verify_nonce( $state, 'wp2-manifest' ) ) {
			wp_die( esc_html__( 'Invalid callback request. Please restart the GitHub App installation.', 'wp2-update' ) );
		}

		?>
		<div id="wp2-update-github-callback" class="wrap">
			<p><?php esc_html_e( 'Completing GitHub App setup…', 'wp2-update' ); ?></p>
		</div>
		<?php
	}

}
