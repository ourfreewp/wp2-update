<?php

namespace WP2\Update\Admin\Assets;

use WP2\Update\Admin\DashboardData;
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
		// Ensure assets are enqueued only for plugin-specific admin screens
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
		self::localize_script_data( $main_script_handle, $manifest );
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
	private static function localize_script_data( string $handle, array $manifest ): void {
		$callback_url = admin_url( 'admin.php?page=wp2-update-github-callback' );
		$state        = DashboardData::get_state();
		$apps         = $state['apps'] ?? [];
		$selected     = $state['selectedAppId'] ?? ( $apps[0]['id'] ?? null );
		$packages     = [
			'all'      => $state['packages'] ?? [],
			'managed'  => $state['managedPackages'] ?? [],
			'unlinked' => $state['unlinkedPackages'] ?? [],
		];
		$connection   = $state['connectionStatus'] ?? null;

        $data = [
            'nonce'          => wp_create_nonce( 'wp_rest' ),
            'apiRoot'        => esc_url_raw( rest_url( 'wp2-update/v1/' ) ),
            'redirectUrl'    => esc_url_raw( admin_url( 'admin.php?page=wp2-update' ) ),
            'siteName'       => get_bloginfo( 'name' ),
            'manifest'       => $manifest,
            'apps'           => $apps,
            'packages'       => $packages['all'],
            'managedPackages'=> $packages['managed'],
            'unlinkedPackages' => $packages['unlinked'],
            'selectedAppId'  => $selected,
            'app_id'         => $selected,
            'connectionStatus' => $connection,
            'githubCallback' => [
				'clientId'   => get_option( 'github_client_id', '' ),
				'callbackUrl'=> esc_url_raw( $callback_url ),
			],
		];

		if ( isset( $packages['error'] ) && is_string( $packages['error'] ) ) {
			$data['packageError'] = $packages['error'];
		}

		wp_localize_script( $handle, 'wp2UpdateData', $data );
	}

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
		$base_dir = trailingslashit( WP2_UPDATE_PLUGIN_DIR ) . 'dist/';
		$candidates = apply_filters(
			'wp2_update_manifest_candidates',
			[
				'.vite/manifest.json',
				'manifest.json',
			]
		);

		foreach ( $candidates as $relative_path ) {
			$manifest_path = $base_dir . $relative_path;
			Logger::log( 'INFO', 'Attempting to load manifest from: ' . $manifest_path );

			if ( ! file_exists( $manifest_path ) ) {
				continue;
			}

			$manifest_contents = file_get_contents( $manifest_path );
			if ( false === $manifest_contents ) {
				Logger::log( 'ERROR', 'Failed to read manifest file at: ' . $manifest_path );
				continue;
			}

			$manifest = json_decode( $manifest_contents, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				Logger::log( 'ERROR', 'Failed to decode manifest JSON (' . $relative_path . '): ' . json_last_error_msg() );
				continue;
			}

			return is_array( $manifest ) ? $manifest : null;
		}

		self::log_manifest_error(
			sprintf(
				'Manifest file not found. Checked: %s',
				implode( ', ', array_map( static fn( $path ) => $base_dir . $path, $candidates ) )
			)
		);

		return null;
	}

	/**
	 * Resolve a manifest entry by matching potential keys or sources.
	 *
	 * @param array<string,mixed> $manifest Full manifest array.
	 * @param string[]            $candidates Keys or src values to search for.
	 */
	private static function resolve_manifest_entry( array $manifest, array $candidates ): ?array {
		foreach ( $candidates as $candidate ) {
			if ( isset( $manifest[ $candidate ] ) && is_array( $manifest[ $candidate ] ) ) {
				return $manifest[ $candidate ];
			}
		}

		foreach ( $manifest as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$entry_values = [
				$entry['src'] ?? null,
				$entry['file'] ?? null,
				$entry['name'] ?? null,
			];

			if ( array_intersect( array_filter( $entry_values ), $candidates ) ) {
				return $entry;
			}
		}

		return null;
	}

	/**
	 * Enqueues stylesheets based on the manifest data.
	 */
	private static function enqueue_styles_from_manifest( array $manifest ): void {
		$entry = self::resolve_manifest_entry(
			$manifest,
			apply_filters(
				'wp2_update_style_candidates',
				[
					'assets/styles/admin-main.scss',
					'admin-style.css',
					'admin-main.css',
				]
			)
		);

		if ( $entry && ! empty( $entry['file'] ) ) {
			wp_enqueue_style(
				'wp2-update-admin-main',
				WP2_UPDATE_PLUGIN_URL . 'dist/' . ltrim( $entry['file'], '/' ),
				[],
				null
			);
		} else {
			Logger::log( 'ERROR', 'Admin stylesheet entry missing from manifest.' );
		}
	}

	/**
	 * Enqueues JavaScript files based on the manifest data.
	 */
	private static function enqueue_scripts_from_manifest( array $manifest, string $handle ): void {
		$entry = self::resolve_manifest_entry(
			$manifest,
			[
				'assets/scripts/admin-main.js',
				'admin-main.js',
			]
		);

		if ( $entry && ! empty( $entry['file'] ) ) {
			$relative_file  = ltrim( $entry['file'], '/' );
			$script_path    = WP2_UPDATE_PLUGIN_DIR . 'dist/' . $relative_file;
			$script_version = file_exists( $script_path ) ? filemtime( $script_path ) : time();

			wp_enqueue_script(
				$handle,
				WP2_UPDATE_PLUGIN_URL . 'dist/' . $relative_file,
				[ 'wp-i18n', 'wp-api', 'wp-api-fetch' ], // WordPress internationalization as a dependency.
				$script_version, // Use file modification time or current timestamp for cache busting
				true // Load script in the footer
			);
		} else {
			Logger::log( 'ERROR', 'Admin script entry missing from manifest.' );
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