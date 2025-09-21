<?php
namespace WP2\Update\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP2\Update\Core\API\GitHubApp\Init as GitHubApp;

use const HOUR_IN_SECONDS;

/**
 * A collection of stateless, static utility methods used across the plugin.
 */
final class SharedUtils {

	/**
	 * @param GitHubApp $github_app
	 * @param string    $app_slug
	 * @param string    $repo
	 * @param int       $count
	 * @return array<int,mixed>
	 */
	public static function get_all_releases( GitHubApp $github_app, string $app_slug, string $repo, int $count = 10 ): array {
		$cache_key = 'wp2_releases_' . md5( $repo );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : [];
		}

		$res = $github_app->gh( $app_slug, 'GET', "/repos/{$repo}/releases", [ 'query' => [ 'per_page' => $count ] ] );

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

		set_transient( $cache_key, $releases, HOUR_IN_SECONDS );
		return $releases;
	}

	/**
	 * @return int
	 */
	public static function get_updates_count(): int {
		$themes  = get_site_transient( 'update_themes' );
		$plugins = get_site_transient( 'update_plugins' );

		$updates_count = 0;

		if ( ! empty( $themes->response ) && is_array( $themes->response ) ) {
			$updates_count += count( $themes->response );
		}

		if ( ! empty( $plugins->response ) && is_array( $plugins->response ) ) {
			$updates_count += count( $plugins->response );
		}

		return $updates_count;
	}

	/**
	 * @param string|null $version
	 * @return string
	 */
	public static function normalize_version( ?string $version ): string {
		return ltrim( $version ?? '0.0.0', 'v' );
	}

	/**
	 * @param array<string,mixed> $release
	 * @return string|null
	 */
	public static function get_zip_url_from_release( array $release ): ?string {
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
	 * @param string|null $uri
	 * @return string|null
	 */
	public static function normalize_repo( ?string $uri ): ?string {
		if ( empty( $uri ) ) {
			return null;
		}
		if ( preg_match( '/(?:https?:\/\/github\.com\/)?([^\/]+\/[^\/]+)/', $uri, $matches ) ) {
			return rtrim( $matches[1], '/' );
		}
		return null;
	}

	/**
	 * @return string
	 */
	public static function get_last_checked_time(): string {
		$last_checked = get_site_transient( 'update_themes' )->last_checked ?? 0;
		return empty( $last_checked ) ? __( 'Never', 'wp2-update' ) : sprintf( __( '%s ago', 'wp2-update' ), human_time_diff( $last_checked ) );
	}

	/**
	 * @param string $data
	 * @return string
	 */
	public static function encrypt( string $data ): string {
		$key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'insecure-fallback-key';
		$iv  = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );
		$enc = openssl_encrypt( $data, 'aes-256-cbc', $key, 0, $iv );
		return base64_encode( $iv . '::' . $enc );
	}

	/**
	 * @param string $data
	 * @return string
	 * @throws \RuntimeException
	 */
	public static function decrypt( string $data ): string {
		$key     = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'insecure-fallback-key';
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
}

