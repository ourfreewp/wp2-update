<?php

namespace WP2\Update\Admin\Assets;

final class Manager {
	public static function enqueue_admin_assets(): void {
		// A more robust way to check if we're on the right page
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'wp2-update' ) {
			return;
		}

		// Check if the current admin page matches the plugin's page slug
		$screen = self::get_current_screen();
		if ( ! $screen || $screen->id !== 'toplevel_page_wp2-update' ) {
			return;
		}

		// Handle missing manifest with an admin notice
		$manifest = self::load_manifest();
		if ( ! $manifest ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Failed to load assets manifest for WP2 Update.', 'wp2-update' ) . '</p></div>';
			} );
			return;
		}

		self::enqueue_scripts( $manifest );
		self::enqueue_styles( $manifest );
		self::localize_scripts();

		// Ensure DOM scaffolding is rendered
		add_action( 'admin_footer', function() {
			echo '<div id="wp2-update-root"></div>';
		} );

		// Debugging: Output script tag directly to confirm inclusion
		add_action( 'admin_footer', function () {
			echo '<script>console.log("Debug: admin-main.js script inclusion check.");</script>';
		}, 100 );
	}

	private static function get_current_screen() {
		return function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	}

	private static function load_manifest(): ?array {
		$manifest_path = trailingslashit( WP2_UPDATE_PLUGIN_DIR ) . 'dist/.vite/manifest.json';
		if ( ! file_exists( $manifest_path ) ) {
			return null;
		}

		$manifest = json_decode( (string) file_get_contents( $manifest_path ), true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return null;
		}

		return $manifest;
	}

	private static function enqueue_scripts( array $manifest ): void {
		if ( ! empty( $manifest['assets/scripts/admin-main.js']['file'] ) ) {
			$js           = $manifest['assets/scripts/admin-main.js']['file'];
			$version      = $manifest['assets/scripts/admin-main.js']['hash'] ?? null;
			$dependencies = array_merge( [ 'wp-i18n' ], $manifest['assets/scripts/admin-main.js']['imports'] ?? [] );

			wp_enqueue_script(
				'wp2-update-admin-main',
				WP2_UPDATE_PLUGIN_URL . 'dist/' . $js,
				$dependencies,
				$version,
				true
			);

			// Ensure the __ function is globally available before the main script executes
			wp_add_inline_script(
				'wp2-update-admin-main',
				'window.__ = window.__ || (window.wp && window.wp.i18n && window.wp.i18n.__ ? window.wp.i18n.__ : (s) => s);',
				'before'
			);
		}
	}

	private static function enqueue_styles( array $manifest ): void {
		if ( ! empty( $manifest['assets/styles/admin-main.scss']['file'] ) ) {
			$css = $manifest['assets/styles/admin-main.scss']['file'];
			wp_enqueue_style( 'wp2-update-admin-main', WP2_UPDATE_PLUGIN_URL . 'dist/' . $css, [], null );
		}
	}

	private static function localize_scripts(): void {
		$data = [
			'pluginUrl' => WP2_UPDATE_PLUGIN_URL,
			'restBase'  => esc_url_raw( rest_url( 'wp2-update/v1' ) ),
			'apiRoot'   => esc_url_raw( rest_url( 'wp2-update/v1/' ) ), // Re-added namespace with trailing slash
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'githubAppManifest' => json_encode([
				'name' => get_bloginfo('name') . ' Updater',
				'url' => home_url(),
				'public' => false,
				'callback_urls' => [home_url()],
				'redirect_url' => admin_url('tools.php?page=wp2-update-callback'),
				'default_permissions' => [
					'contents' => 'read',
					'metadata' => 'read',
				],
				'default_events' => ['release'],
			]),
		];

		// Localize the script
		wp_localize_script(
			'wp2-update-admin-main',
			'wp2UpdateData',
			$data
		);

		// Debugging: Log the handle and data passed to wp_localize_script
		do_action( 'qm/debug', 'wp_localize_script called with handle: wp2-update-admin-main and data: ' . print_r( $data, true ) );

		global $wp_scripts;
		if ( ! in_array( 'wp2-update-admin-main', $wp_scripts->queue, true ) ) {
			do_action( 'qm/debug', 'Error: wp2-update-admin-main script not enqueued before localization.' );
		}

		// Debugging: Log the handle and data passed to wp_localize_script
		do_action( 'qm/debug', 'wp_localize_script called with handle: wp2-update-admin-main and data: ' . print_r( $data, true ) );

		// Remove forced inline script for testing
		add_action( 'admin_footer', function() {
			echo '<script>console.log("Testing wp_localize_script: wp2UpdateData:", typeof wp2UpdateData !== "undefined" ? wp2UpdateData : "wp2UpdateData is not defined.");</script>';
		}, 100 );
	}


}