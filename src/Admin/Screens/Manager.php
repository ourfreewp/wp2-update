<?php

namespace WP2\Update\Admin\Screens;

/**
 * Handles rendering the admin screen for the WP2 Update plugin.
 * Renders the minimal "app shell" for the JavaScript SPA.
 */
final class Manager {

	public static function render(): void {
		?>
		<div id="wp2-update-app" class="wp2-container">
			<div class="wp2-header">
				<div class="wp2-header__inner">
					<div class="wp2-header__content">
					<h1 class="wp2-main-title"><?php esc_html_e( 'WP2 Update', 'wp2-update' ); ?></h1>
					<p class="wp2-description">
						<?php esc_html_e( 'Manage and update your GitHub-hosted WordPress plugins and themes directly from your WordPress admin dashboard.', 'wp2-update' ); ?>
					</p>
					</div>
					<div class="wp2-header__actions">
						<?php
						$actions = [
							'sync'   => [
								'label' => __( 'Sync Now', 'wp2-update' ),
								'class' => 'wp2-button--primary',
								'id'    => 'wp2-sync-button',
							],
							'add'    => [
								'label' => __( 'Add New', 'wp2-update' ),
								'class' => 'wp2-button--secondary',
								'id'    => 'wp2-add-button',
							],
							'support' => [
								'label' => __( 'Support', 'wp2-update' ),
								'class' => 'wp2-button--tertiary',
								'id'    => 'wp2-support-button',
								'url'   => 'https://wp2.io/support?utm_source=plugin&utm_medium=wp-admin&utm_campaign=wp2-update',
								'target' => '_blank',
								'rel'    => 'noopener noreferrer',
							],
							'docs'   => [
								'label' => __( 'Docs', 'wp2-update' ),
								'class' => 'wp2-button--tertiary',
								'id'    => 'wp2-docs-button',
								'url'   => 'https://wp2.io/docs/wp2-update?utm_source=plugin&utm_medium=wp-admin&utm_campaign=wp2-update',
								'target' => '_blank',
								'rel'    => 'noopener noreferrer',
							],
						];
						
						foreach ( $actions as $action ) {
							$attributes = '';
							if ( isset( $action['url'] ) ) {
								$attributes .= ' href="' . esc_url( $action['url'] ) . '"';
							} else {
								$attributes .= ' type="button"';
							}
							if ( isset( $action['target'] ) ) {
								$attributes .= ' target="' . esc_attr( $action['target'] ) . '"';
							}
							if ( isset( $action['rel'] ) ) {
								$attributes .= ' rel="' . esc_attr( $action['rel'] ) . '"';
							}
							printf(
								'<a id="%1$s" class="wp2-button %2$s"%3$s>%4$s</a>',
								esc_attr( $action['id'] ),
								esc_attr( $action['class'] ),
								$attributes,
								esc_html( $action['label'] )
							);
						}
						?>
					</div>
				</div>
			</div>

			<!-- Tab Navigation -->
			<nav class="wp2-tabs" data-tabs aria-label="<?php esc_attr_e( 'WP2 Update sections', 'wp2-update' ); ?>">
				<ul role="tablist">
					<li>
						<a
							id="wp2-tab-dashboard"
							href="#wp2-dashboard-root"
							class="wp2-tab-link active"
							role="tab"
							aria-controls="wp2-dashboard-root"
							aria-selected="true"
						>
							<?php esc_html_e( 'Dashboard', 'wp2-update' ); ?>
						</a>
					</li>
					<li>
						<a
							id="wp2-tab-packages"
							href="#wp2-packages-root"
							class="wp2-tab-link"
							role="tab"
							aria-controls="wp2-packages-root"
							aria-selected="false"
						>
							<?php esc_html_e( 'Packages', 'wp2-update' ); ?>
						</a>
					</li>
					<li>
						<a
							id="wp2-tab-apps"
							href="#wp2-apps-root"
							class="wp2-tab-link"
							role="tab"
							aria-controls="wp2-apps-root"
							aria-selected="false"
						>
							<?php esc_html_e( 'Apps', 'wp2-update' ); ?>
						</a>
					</li>
					<li>
						<a
							id="wp2-tab-settings"
							href="#wp2-settings-root"
							class="wp2-tab-link"
							role="tab"
							aria-controls="wp2-settings-root"
							aria-selected="false"
						>
							<?php esc_html_e( 'Settings', 'wp2-update' ); ?>
						</a>
					</li>
				</ul>
			</nav>

			<!-- Tab Content -->
			<div id="wp2-tab-content">
				<div
					id="wp2-dashboard-root"
					class="wp2-tab-panel wp2-dashboard-panel active"
					data-tabs-pane
					role="tabpanel"
					aria-labelledby="wp2-tab-dashboard"
				></div>
				<div
					id="wp2-packages-root"
					class="wp2-tab-panel wp2-packages-panel"
					data-tabs-pane
					role="tabpanel"
					aria-labelledby="wp2-tab-packages"
					hidden
				></div>
				<div
					id="wp2-apps-root"
					class="wp2-tab-panel wp2-apps-panel"
					data-tabs-pane
					role="tabpanel"
					aria-labelledby="wp2-tab-apps"
					hidden
				></div>
				<div
					id="wp2-settings-root"
					class="wp2-tab-panel wp2-settings-panel"
					data-tabs-pane
					role="tabpanel"
					aria-labelledby="wp2-tab-settings"
					hidden
				></div>
			</div>

			<!-- Modals -->
			<?php self::render_modal(); ?>
		</div>
		<noscript>
			<p><?php esc_html_e( 'JavaScript is required to run the WP2 Update app. Please enable JavaScript in your browser settings.', 'wp2-update' ); ?></p>
		</noscript>
		<?php
	}

	private static function render_modal(): void {
		?>
		<div id="wp2-disconnect-modal" class="wp2-modal" hidden>
			<div class="wp2-modal-content">
				<h3 class="wp2-modal-heading"><?php esc_html_e( 'Confirm Action', 'wp2-update' ); ?></h3>
				<p class="wp2-modal-message"></p>
				<div class="wp2-modal-actions">
					<button class="wp2-button wp2-button--secondary" data-wp2-action="cancel-disconnect">
						<?php esc_html_e( 'Cancel', 'wp2-update' ); ?>
					</button>
					<button class="wp2-button wp2-button--danger" data-wp2-action="confirm-disconnect">
						<?php esc_html_e( 'Confirm', 'wp2-update' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	public static function render_github_callback(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp2-update' ) );
		}
		?>
		<div id="wp2-update-github-callback" class="wp2-wrap">
			<p class="wp2-p"><?php esc_html_e( 'Completing GitHub App setup…', 'wp2-update' ); ?></p>
		</div>
		<?php
	}

}
