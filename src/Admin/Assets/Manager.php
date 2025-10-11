<?php

namespace WP2\Update\Admin\Assets;

use WP2\Update\Utils\Logger;
/**
 * Manages the enqueuing of admin-facing scripts and styles.
 * Designed to work with a Vite manifest for modern asset handling.
 */
final class Manager {

	/**
	 * Registers the necessary hooks for enqueuing assets.
	 */
	public static function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
	}

	/**
	 * Checks if the current page is a plugin screen, then enqueues assets.
	 * This is the primary callback for the 'admin_enqueue_scripts' hook.
	 */
	public static function enqueue_assets(): void {
		// Abort if we're not on a screen belonging to our plugin.
		if ( ! self::is_plugin_screen() ) {
			return;
		}

		$manifest = self::load_manifest();
		if ( ! $manifest ) {
			// If the manifest is missing, show an error and stop.
			add_action( 'admin_notices', [ self::class, 'render_manifest_error' ] );
			return;
		}

		$main_script_handle = 'wp2-update-admin-main';

		self::enqueue_styles_from_manifest( $manifest );
		self::enqueue_scripts_from_manifest( $manifest, $main_script_handle );
		self::localize_script_data( $main_script_handle );
	}

	/**
	 * Checks if the current admin screen belongs to this plugin.
	 */
	private static function is_plugin_screen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		// List of screen IDs where our assets should be loaded.
		$allowed_screens = [
			'toplevel_page_wp2-update',
			'admin_page_wp2-update-github-callback',
		];

		return in_array( $screen->id, $allowed_screens, true );
	}

	/**
	 * Localizes the main script with data from PHP.
	 */
	private static function localize_script_data( string $handle ): void {
		$callback_url = admin_url( 'admin.php?page=wp2-update-github-callback' );
		$redirect_url = admin_url( 'admin.php?page=wp2-update-github-callback' );

		$data = [
			'apiRoot'  => esc_url_raw( rest_url( 'wp2-update/v1/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'siteName' => get_bloginfo( 'name' ),
			'redirectUrl' => esc_url_raw( $callback_url ),
			'manifest' => json_decode( wp_json_encode( [
				'name'                => get_bloginfo( 'name' ) . ' Updater',
				'url'                 => home_url(),
				'public'              => false,
				'callback_urls'       => [ $callback_url ],
				'setup_url'           => esc_url_raw( $redirect_url ),
				'setup_on_update'     => false,
				'default_permissions' => [
					'contents' => 'read',
					'metadata' => 'read',
				],
				'default_events'      => [ 'release' ],
			] ), true ),
		];

		if (self::is_plugin_screen() && ($_GET['page'] ?? '') === 'wp2-update-github-callback') {
			$data['githubCode'] = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : null;
			$data['githubState'] = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : null;
		}

		$data['app_id'] = get_option('wp2_update_app_id', null) ?: null; // Ensure app_id is optional and defaults to null

		wp_localize_script( $handle, 'wp2UpdateData', $data );
	}

	/**
	 * Logs errors related to the Vite manifest.
	 */
	private static function log_manifest_error( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::log('ERROR', 'Manifest Error: ' . $message);
		}
	}

	/**
	 * Loads and decodes the Vite manifest file.
	 *
	 * @return array|null The manifest data or null on failure.
	 */
	private static function load_manifest(): ?array {
		$manifest_path = trailingslashit( WP2_UPDATE_PLUGIN_DIR ) . 'dist/.vite/manifest.json';
		Logger::log('INFO', 'Attempting to load manifest from: ' . $manifest_path);
		if ( ! file_exists( $manifest_path ) ) {
			Logger::log('ERROR', 'Manifest file not found at: ' . $manifest_path);
			self::log_manifest_error( 'Manifest file not found at ' . $manifest_path );
			return null;
		}

		$manifest_contents = file_get_contents( $manifest_path );
		if ( false === $manifest_contents ) {
			Logger::log('ERROR', 'Failed to read manifest file at: ' . $manifest_path);
			self::log_manifest_error( 'Failed to read manifest file at ' . $manifest_path );
			return null;
		}

		$manifest = json_decode( $manifest_contents, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			Logger::log('ERROR', 'Failed to decode manifest JSON: ' . json_last_error_msg());
			self::log_manifest_error( 'Failed to decode manifest JSON: ' . json_last_error_msg() );
			return null;
		}

		return $manifest;
	}

	/**
	 * Enqueues stylesheets based on the manifest data.
	 */
	private static function enqueue_styles_from_manifest( array $manifest ): void {
		$style_entry = 'assets/styles/admin-main.scss';

		if ( ! empty( $manifest[ $style_entry ]['file'] ) ) {
			wp_enqueue_style(
				'wp2-update-admin-main',
				WP2_UPDATE_PLUGIN_URL . 'dist/' . $manifest[ $style_entry ]['file'],
				[],
				null
			);
		}
	}

	/**
	 * Enqueues JavaScript files based on the manifest data.
	 */
	private static function enqueue_scripts_from_manifest( array $manifest, string $handle ): void {
		$script_entry = 'assets/scripts/admin-main.js';

		if ( ! empty( $manifest[ $script_entry ]['file'] ) ) {
			$script_path    = WP2_UPDATE_PLUGIN_DIR . 'dist/' . $manifest[ $script_entry ]['file'];
			$script_version = file_exists( $script_path ) ? filemtime( $script_path ) : time();

			wp_enqueue_script(
				$handle,
				WP2_UPDATE_PLUGIN_URL . 'dist/' . $manifest[ $script_entry ]['file'],
				[ 'wp-i18n', 'wp-api', 'wp-api-fetch' ], // WordPress internationalization as a dependency.
				$script_version, // Use file modification time or current timestamp for cache busting
				true // Load script in the footer
			);
		}
	}

	/**
	 * Adds type="module" to the script tag for the admin-main.js script.
	 */
	public static function add_module_type_to_script( $tag, $handle, $src ) {
		// Check if the script handle matches our admin script.
		if ( 'wp2-update-admin-main' === $handle ) {
			// Modify the script tag to include type="module".
			$tag = '<script type="module" src="' . esc_url( $src ) . '"></script>';
		}
		return $tag;
	}

	/**
	 * Renders an admin notice when the asset manifest is missing.
	 */
	public static function render_manifest_error(): void {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Failed to load asset manifest for WP2 Update. Please run your build process.', 'wp2-update' ); ?>
			</p>
		</div>
		<?php
	}
}

// Register the filter to modify the script tag for `admin-main.js`.
add_filter( 'script_loader_tag', [ '\WP2\Update\Admin\Assets\Manager', 'add_module_type_to_script' ], 10, 3 );
