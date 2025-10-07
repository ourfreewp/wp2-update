<?php
namespace WP2\Update\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP2\Update\Core\API\GitHubApp\Init as GitHubApp;
use WP2\Update\Core\API\Service as GitHubService;
use WP_Error;

use const HOUR_IN_SECONDS;

/**
 * A collection of shared utility methods used across the plugin.
 */
final class SharedUtils {

	/**
	 * @var GitHubApp
	 */
	private GitHubApp $github_app;
	private GitHubService $github_service;

	/**
	 * @param GitHubApp $github_app The GitHub App facade used for API calls.
	 * @param GitHubService $github_service The GitHub Service for API calls.
	 */
	public function __construct( GitHubApp $github_app, GitHubService $github_service ) {
		$this->github_app = $github_app;
		$this->github_service = $github_service;
	}

	/**
	 * Retrieves the latest releases for a managed repository and caches the response.
	 *
	 * @param string $app_slug The GitHub App slug handling the repository.
	 * @param string $repo     The repository identifier (owner/repo).
	 * @param int    $count    Number of releases to fetch.
	 * @return array<int,mixed>
	 */
	public function get_all_releases( string $app_slug, string $repo, int $count = 10 ): array {
		$cache_key = 'wp2_releases_' . md5( $repo );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : [];
		}

		$res = $this->github_app->gh(
			$app_slug,
			'GET',
			"/repos/{$repo}/releases",
			[
				'per_page' => $count, // Corrected query parameter structure
			]
		);

		if ( empty( $res['ok'] ) ) {
			Logger::log( 'Error fetching releases for repository: ' . $repo, 'error', 'github' );
			return [];
		}

		$releases = is_array( $res['data'] )
			? array_values(
				array_filter(
					$res['data'],
					static fn( $r ): bool => is_array( $r ) && empty( $r['draft'] ) && empty( $r['prerelease'] )
				)
			)
			: [];

		set_transient( $cache_key, $releases, 6 * HOUR_IN_SECONDS );
		return $releases;
	}

	/**
	 * Counts the number of plugin and theme updates available for the site.
	 *
	 * @return int
	 */
	public function get_updates_count(): int {
		$themes  = get_site_transient( 'update_themes' );
		$plugins = get_site_transient( 'update_plugins' );

		$updates_count = 0;

		if ( is_object( $themes ) && ! empty( $themes->response ) && is_array( $themes->response ) ) {
			$updates_count += count( $themes->response );
		}

		if ( is_object( $plugins ) && ! empty( $plugins->response ) && is_array( $plugins->response ) ) {
			$updates_count += count( $plugins->response );
		}

		return $updates_count;
	}

	/**
	 * Normalizes version tags by stripping leading characters.
	 *
	 * @param string|null $version
	 * @return string
	 */
	public function normalize_version( ?string $version ): string {
		return ltrim( $version ?? '0.0.0', 'v' );
	}

	/**
	 * Locates the first downloadable ZIP asset for a release.
	 *
	 * @param array<string,mixed> $release
	 * @return string|null
	 */
	public function get_zip_url_from_release( array $release ): ?string {
		foreach ( ( $release['assets'] ?? [] ) as $asset ) {
			if (
				isset( $asset['browser_download_url'] )
				&& in_array( $asset['content_type'] ?? '', [ 'application/zip', 'application/x-zip-compressed' ], true )
			) {
				return (string) $asset['browser_download_url'];
			}
		}

		return isset( $release['zipball_url'] ) ? (string) $release['zipball_url'] : null;
	}

	/**
	 * Normalizes GitHub repository identifiers from URIs or slugs.
	 *
	 * @param string|null $uri
	 * @return string|null
	 */
	public function normalize_repo( ?string $uri ): ?string {
		if ( empty( $uri ) ) {
			return null;
		}
		if ( preg_match( '/(?:https?:\/\/github\.com\/)?([^\/]+\/[^\/]+)/', $uri, $matches ) ) {
			return rtrim( $matches[1], '/' );
		}
		if ( strpos( $uri, '/' ) !== false && ! preg_match( '/https?:\/\//', $uri ) ) {
			// Handle owner/repo slugs
			return $uri;
		}
		return null;
	}

	/**
	 * Returns a human readable representation of the last update check time.
	 *
	 * @return string
	 */
	public function get_last_checked_time(): string {
		$update_themes = get_site_transient( 'update_themes' );
		$last_checked  = ( is_object( $update_themes ) && isset( $update_themes->last_checked ) ) ? (int) $update_themes->last_checked : 0;

		if ( empty( $last_checked ) ) {
			return __( 'Never', 'wp2-update' );
		}

		return sprintf( __( '%s ago', 'wp2-update' ), human_time_diff( $last_checked ) );
	}

	/**
	 * Encrypts sensitive values using AES-256-CBC and the AUTH_KEY salt.
	 */
	public static function encrypt( string $data ): string {
		if ( ! defined( 'AUTH_KEY' ) ) {
			// Display admin notice if AUTH_KEY is not defined
			if ( is_admin() ) {
				add_action( 'admin_notices', function() {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Encryption key is not defined. Please set AUTH_KEY in your wp-config.php.', 'wp2-update' ) . '</p></div>';
				});
			}
			throw new \RuntimeException( __( 'Encryption key is not defined. Please set AUTH_KEY in your wp-config.php.', 'wp2-update' ) );
		}

		$key = AUTH_KEY;
		$iv  = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );
		$enc = openssl_encrypt( $data, 'aes-256-cbc', $key, 0, $iv );
		return base64_encode( $iv . '::' . $enc );
	}

	/**
	 * Decrypts values generated by {@see self::encrypt()}.
	 *
	 * @param string $data
	 * @return string
	 * @throws \RuntimeException When the payload cannot be decoded or decrypted.
	 */
	public static function decrypt( string $data ): string {
		if ( ! defined( 'AUTH_KEY' ) ) {
			// Display admin notice if AUTH_KEY is not defined
			if ( is_admin() ) {
				add_action( 'admin_notices', function() {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Encryption key is not defined. Please set AUTH_KEY in your wp-config.php.', 'wp2-update' ) . '</p></div>';
				});
			}
			throw new \RuntimeException( __( 'Encryption key is not defined. Please set AUTH_KEY in your wp-config.php.', 'wp2-update' ) );
		}

		$key     = AUTH_KEY;
		$decoded = base64_decode( $data, true );
		if ( false === $decoded || false === strpos( $decoded, '::' ) ) {
			throw new \RuntimeException( __( 'Failed to decode encrypted data.', 'wp2-update' ) );
		}
		list( $iv, $enc ) = explode( '::', $decoded, 2 );
		$dec = openssl_decrypt( (string) $enc, 'aes-256-cbc', $key, 0, $iv );
		if ( false === $dec ) {
			throw new \RuntimeException( __( 'Failed to decrypt data. The WordPress AUTH_KEY may have changed.', 'wp2-update' ) );
		}
		return $dec;
	}

	/**
	 * Finds the main plugin file in a given directory.
	 *
	 * @param string $directory The directory to search for the plugin file.
	 * @return string|null The path to the main plugin file, or null if not found.
	 */
	public function get_plugin_file( string $directory ): ?string {
		$plugin_files = glob( trailingslashit( $directory ) . '*.php' );

		foreach ( $plugin_files as $file ) {
			$contents = file_get_contents( $file );
			if ( strpos( $contents, 'Plugin Name:' ) !== false ) {
				return $file;
			}
		}

		return null;
	}

	/**
	 * Checks if an update is available for a given theme or plugin slug.
	 *
	 * @param string $slug The slug of the theme or plugin.
	 * @return bool True if an update is available, false otherwise.
	 */
	public function is_update_available( string $slug ): bool {
		$themes  = get_site_transient( 'update_themes' );
		$plugins = get_site_transient( 'update_plugins' );

		if ( isset( $themes->response[ $slug ] ) ) {
			return true;
		}

		if ( isset( $plugins->response[ $slug ] ) ) {
			return true;
		}

		return false;
	}

    /**
     * Unified installation logic for themes and plugins.
     *
     * @param string $app_slug The app slug to use for authentication.
     * @param string $repo     The repository name ("owner/repo").
     * @param string $version  The tag name to install.
     * @param string $type     The type of package ("theme" or "plugin").
     * @return true|WP_Error True on success, WP_Error on failure.
     */
    public function install_package( string $app_slug, string $repo, string $version, string $type ) {
        Logger::log( "Attempting to install {$type} {$repo} version {$version} using app {$app_slug}.", 'info', 'install' );

        $release_res = $this->github_app->gh($app_slug, 'GET', "/repos/{$repo}/releases/tags/{$version}");
        if (empty($release_res['ok'])) {
            Logger::log("Install failed: Could not fetch release info for tag {$version}. Error: " . ($release_res['error'] ?? 'Unknown'), 'error', 'install');
            return new WP_Error('release_fetch_failed', __('Could not fetch release information from GitHub.', 'wp2-update'));
        }

        $zip_url = $this->get_zip_url_from_release( $release_res['data'] );
        if ( ! $zip_url ) {
            Logger::log( "Install failed: No ZIP asset found for tag {$version}.", 'error', 'install' );
            return new WP_Error( 'no_zip_asset', __( 'The selected release does not contain a valid ZIP file asset.', 'wp2-update' ) );
        }

        if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
            Logger::log( 'Install failed: File modifications are disabled (DISALLOW_FILE_MODS).', 'error', 'install' );
            return new WP_Error( 'file_mods_disabled', __( 'File modifications are disabled in your WordPress configuration.', 'wp2-update' ) );
        }

        // Load WordPress Core files required for installation.
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        if ( $type === 'theme' ) {
            require_once ABSPATH . 'wp-admin/includes/theme.php';
            $skin = new \Automatic_Upgrader_Skin();
            $upgrader = new \Theme_Upgrader( $skin );
        } else {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            $skin = new \Automatic_Upgrader_Skin();
            $upgrader = new \Plugin_Upgrader( $skin );
        }

        // Use authenticated client to download the file.
        $temp_zip_file = $this->github_service->download_to_temp_file( $app_slug, $zip_url );
        if ( is_wp_error( $temp_zip_file ) ) {
            return $temp_zip_file;
        }

        // Install the package.
        $result = $upgrader->install( $temp_zip_file );

        // Clean up the temporary file, regardless of success or failure.
        if ( $temp_zip_file && file_exists( $temp_zip_file ) ) {
            @unlink( $temp_zip_file );
        }

        if ( is_wp_error( $result ) ) {
            Logger::log( "Install failed: " . $result->get_error_message(), 'error', 'install' );
            return $result;
        }

        Logger::log( ucfirst($type) . " {$repo} version {$version} installed successfully.", 'success', 'install');
        return true;
    }
}
