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
		<div class="wrap">
			<h1><?php esc_html_e( 'WP2 Updates', 'wp2-update' ); ?></h1>

			<div id="wp2-update-app"></div>

			<div id="configure-manifest" class="workflow-step">
				<h2><?php esc_html_e( 'Configure GitHub Manifest', 'wp2-update' ); ?></h2>
				<p><?php esc_html_e( 'Follow the steps below to configure your GitHub App manifest.', 'wp2-update' ); ?></p>
			</div>

			<div id="wp2-package-table" class="workflow-step">
				<h2><?php esc_html_e( 'Manage Packages', 'wp2-update' ); ?></h2>
				<p><?php esc_html_e( 'View and manage your installed packages.', 'wp2-update' ); ?></p>
			</div>

			<?php
			self::render_modal(
				'disconnect-modal',
				__( 'Are you sure you want to disconnect?', 'wp2-update' ),
				[
					[ 'class' => 'modal-cancel button button-secondary', 'label' => __( 'Cancel', 'wp2-update' ) ],
					[ 'class' => 'modal-confirm button button-danger', 'label' => __( 'Confirm', 'wp2-update' ) ],
				]
			);
			?>
		</div>
		<?php
	}

	/**
	 * Renders a modal dialog.
	 *
	 * @param string $id      The ID of the modal.
	 * @param string $message The message to display in the modal.
	 * @param array  $actions An array of actions (buttons) for the modal.
	 */
	private static function render_modal( string $id, string $message, array $actions ): void {
		?>
		<div id="<?php echo esc_attr( $id ); ?>" class="wp2-modal" hidden>
			<div class="modal-content">
				<p class="modal-message"><?php echo esc_html( $message ); ?></p>
				<div class="modal-actions">
					<?php foreach ( $actions as $action ) : ?>
						<button class="<?php echo esc_attr( $action['class'] ); ?>">
							<?php echo esc_html( $action['label'] ); ?>
						</button>
					<?php endforeach; ?>
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
