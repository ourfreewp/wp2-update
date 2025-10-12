<?php

namespace WP2\Update\Admin;

use WP2\Update\Config;
use WP2\Update\Core\API\ConnectionService;
use WP2\Update\Core\Updates\PackageService;
use WP2\Update\Utils\Logger;
use function __;
use function array_filter;
use function array_map;
use function array_merge;
use function array_values;
use function count;
use function get_option;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function sanitize_key;
use function sanitize_text_field;
use function wp_unslash;

/**
 * Provides preloaded data for the admin dashboard.
 *
 * This centralises how apps and packages are prepared so both the
 * server-rendered markup and the bootstrapped JavaScript state stay in sync.
 */
final class DashboardData {
	/**
	 * Package service used to collect managed and unlinked packages.
	 */
	private static ?PackageService $package_service = null;

	/**
	 * Handles GitHub connection status checks.
	 */
	private static ?ConnectionService $connection_service = null;

	/**
	 * Cached package payload.
	 *
	 * @var array<string,mixed>|null
	 */
	private static ?array $packages_cache = null;

	/**
	 * Cached apps payload.
	 *
	 * @var array<int,array<string,mixed>>|null
	 */
	private static ?array $apps_cache = null;

	/**
	 * Cached connection status payload.
	 *
	 * @var array<string,mixed>|null
	 */
	private static ?array $connection_cache = null;

	/**
	 * Supported tab slugs.
	 *
	 * @var string[]
	 */
	private static array $allowed_tabs = [ 'packages', 'apps' ];

	/**
	 * Bootstrap the data provider with required dependencies.
	 */
	public static function bootstrap( PackageService $package_service, ?ConnectionService $connection_service = null ): void {
		self::$package_service = $package_service;
		self::$connection_service = $connection_service;
		self::$packages_cache  = null;
		self::$apps_cache      = null;
		self::$connection_cache = null;
	}

	/**
	 * Retrieve the preloaded state for the SPA bootstrap.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_state(): array {
		$apps     = self::get_apps();
		$packages = self::get_packages();
		$connection = self::get_connection_status();

		return [
			'apps'              => $apps,
			'packages'          => $packages['all'],
			'managedPackages'   => $packages['managed'],
			'unlinkedPackages'  => $packages['unlinked'],
			'selectedAppId'     => $apps[0]['id'] ?? null,
			'packageError'      => $packages['error'] ?? null,
			'connectionStatus'  => $connection,
			'health'            => [
				'phpVersion' => phpversion(),
				'dbStatus' => 'OK',
				'activePlugins' => count(get_option('active_plugins', [])),
			],
			'stats'            => [
				'totalUpdates' => 0,
				'successfulUpdates' => 0,
				'failedUpdates' => 0,
			]
		];
	}

	/**
	 * Retrieve preloaded apps from storage.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_apps(): array {
		if ( null !== self::$apps_cache ) {
			return self::$apps_cache;
		}

		$raw = get_option( Config::OPTION_APPS, [] );
		if ( ! is_array( $raw ) ) {
			self::$apps_cache = [];
			return self::$apps_cache;
		}

		$apps = array_values(
			array_filter(
				array_map(
					static function ( $app ) {
						if ( ! is_array( $app ) ) {
							return null;
						}

						$id = isset( $app['id'] ) ? (string) $app['id'] : '';
						if ( '' === $id ) {
							return null;
						}

						$managed = [];
						if ( isset( $app['managed_repositories'] ) ) {
							$managed = is_array( $app['managed_repositories'] )
								? array_values(
									array_filter(
										$app['managed_repositories'],
										static fn ( $repo ) => is_string( $repo ) && '' !== $repo
									)
								)
								: [];
						}

						$account_type = sanitize_key( $app['account_type'] ?? 'user' );
						$account_type = $account_type ?: 'user';
						$status       = sanitize_key( $app['status'] ?? ( $app['installation_id'] ? 'installed' : 'pending' ) );
						$status       = $status ?: 'pending';

						$name = isset( $app['name'] ) ? sanitize_text_field( wp_unslash( (string) $app['name'] ) ) : '';
						$name = '' === $name ? __('Unnamed App', 'wp2-update') : $name;

						return [
							'id'                   => $id,
							'name'                 => $name,
							'status'               => $status,
							'account_type'         => $account_type,
							'accountType'          => $account_type, // Legacy key for existing JS helpers.
							'package_count'        => count( $managed ),
							'packageCount'         => count( $managed ),
							'managed_repositories' => $managed,
							'created_at'           => isset( $app['created_at'] ) ? sanitize_text_field( (string) $app['created_at'] ) : '',
							'updated_at'           => isset( $app['updated_at'] ) ? sanitize_text_field( (string) $app['updated_at'] ) : '',
							'installation_id'      => isset( $app['installation_id'] ) ? (string) $app['installation_id'] : '',
						];
					},
					$raw
				)
			)
		);

		self::$apps_cache = $apps;

		return self::$apps_cache;
	}

	/**
	 * Retrieve preloaded packages grouped by managed/unlinked.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_packages(): array {
		if ( null !== self::$packages_cache ) {
			return self::$packages_cache;
		}

		Logger::log('DEBUG', 'get_packages method called.');

		self::$packages_cache = self::collect_packages();

		// Include unlinked packages in the main packages array for rendering.
		self::$packages_cache['all'] = array_merge(
			self::$packages_cache['managed'] ?? [],
			self::$packages_cache['unlinked'] ?? []
		);

		Logger::log('DEBUG', 'get_packages result: ' . print_r(self::$packages_cache, true));

		return self::$packages_cache;
	}

	/**
	 * Resolve the label for the latest release/version display.
	 */
	private static function resolve_latest_label( array $package ): string {
		$latest = $package['latest'] ?? null;

		if ( is_array( $latest ) ) {
			$label = $latest['label'] ?? $latest['tag'] ?? $latest['tag_name'] ?? null;
			if ( is_string( $label ) && '' !== $label ) {
				return $label;
			}
		} elseif ( is_string( $latest ) && '' !== $latest ) {
			return $latest;
		}

		if ( isset( $package['github_data'] ) && is_array( $package['github_data'] ) ) {
			$github_latest = $package['github_data']['latest_release'] ?? null;

			if ( is_string( $github_latest ) && '' !== $github_latest ) {
				return $github_latest;
			}

			if ( is_array( $github_latest ) ) {
				$label = $github_latest['name'] ?? $github_latest['tag_name'] ?? null;
				if ( is_string( $label ) && '' !== $label ) {
					return $label;
				}
			}
		}

		$version = $package['version'] ?? '';

		return is_string( $version ) ? $version : '';
	}

	/**
	 * Normalise a raw package structure to the shape expected by the UI.
	 */
	private static function normalize_package( $package, bool $managed ): ?array {
		if ( ! is_array( $package ) ) {
			return null;
		}

		$repo = sanitize_text_field(
			wp_unslash(
				(string) ( $package['repo'] ?? $package['repository'] ?? '' )
			)
		);

		$name = sanitize_text_field(
			wp_unslash(
				(string) ( $package['name'] ?? $package['title'] ?? $repo )
			)
		);

		$installed = sanitize_text_field(
			wp_unslash(
				(string) ( $package['installed'] ?? $package['version'] ?? '' )
			)
		);

		$latest_label = self::resolve_latest_label( $package );

		$status = sanitize_key( $package['status'] ?? '' );
		if ( '' === $status ) {
			if ( '' !== $installed && '' !== $latest_label ) {
				$status = $installed === $latest_label ? 'up_to_date' : 'outdated';
			} else {
				$status = $managed ? 'managed' : 'unlinked';
			}
		}

		$app_id = $package['app_id'] ?? $package['app_uid'] ?? null;
		if ( is_numeric( $app_id ) ) {
			$app_id = (string) $app_id;
		} elseif ( is_string( $app_id ) ) {
			$app_id = sanitize_text_field( wp_unslash( $app_id ) );
		} else {
			$app_id = null;
		}

		$stars  = isset( $package['stars'] ) ? (int) $package['stars'] : 0;
		$issues = isset( $package['issues'] ) ? (int) $package['issues'] : 0;

		$is_managed = $managed ? true : (bool) ( $package['is_managed'] ?? $package['app_id'] ?? $package['app_uid'] );

		return array_merge(
			$package,
			[
				'name'          => '' !== $name ? $name : __( 'Unknown Package', 'wp2-update' ),
				'repo'          => $repo,
				'installed'     => $installed,
				'latest'        => $package['latest'] ?? $latest_label,
				'latest_label'  => $latest_label,
				'status'        => $status,
				'app_id'        => $app_id,
				'is_managed'    => $is_managed,
				'stars'         => $stars,
				'issues'        => $issues,
			]
		);
	}

	/**
	 * Collect normalised packages and cache the result.
	 *
	 * @return array<string,mixed>
	 */
	private static function collect_packages(): array {
		if ( null === self::$package_service ) {
			return [
				'managed'  => [],
				'unlinked' => [],
				'all'      => [],
			];
		}

		return self::$package_service->get_all_packages();
	}

	/**
	 * Retrieve connection status data, if available.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_connection_status(): array {
		if ( null !== self::$connection_cache ) {
			return self::$connection_cache;
		}

		if ( null === self::$connection_service ) {
			self::$connection_cache = [
				'status'  => 'loading',
				'message' => '',
				'details' => [],
			];
			return self::$connection_cache;
		}

		try {
			if ( ! self::$connection_service->has_credentials() ) {
				self::$connection_cache = [
					'status'  => 'not_configured',
					'message' => '',
					'details' => [],
				];
				return self::$connection_cache;
			}
		} catch ( \Throwable $exception ) {
			Logger::log( 'ERROR', 'Unable to determine credential status: ' . $exception->getMessage() );
			self::$connection_cache = [
				'status'  => 'error',
				'message' => __( 'Unable to determine credential status.', 'wp2-update' ),
				'details' => [],
			];
			return self::$connection_cache;
		}

		try {
			$status = self::$connection_service->get_connection_status();
			if ( ! is_array( $status ) ) {
				throw new \RuntimeException( 'Unexpected connection status format.' );
			}

			self::$connection_cache = [
				'status'  => $status['status'] ?? 'error',
				'message' => $status['message'] ?? '',
				'details' => is_array( $status['details'] ?? null ) ? $status['details'] : [],
			];
		} catch ( \Throwable $exception ) {
			Logger::log( 'ERROR', 'Failed to bootstrap connection status: ' . $exception->getMessage() );
			self::$connection_cache = [
				'status'  => 'error',
				'message' => $exception->getMessage(),
				'details' => [],
			];
		}

		return self::$connection_cache;
	}

	/**
	 * Retrieve health data for the dashboard.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_health_data(): array {
		return [
			'phpVersion' => phpversion(),
			'dbStatus' => self::check_database_connection(),
			'activePlugins' => count(get_option('active_plugins', [])),
		];
	}

	/**
	 * Retrieve stats data for the dashboard.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_stats_data(): array {
		return [
			'totalUpdates' => self::get_total_updates(),
			'successfulUpdates' => self::get_successful_updates(),
			'failedUpdates' => self::get_failed_updates(),
		];
	}

	/**
	 * Check database connection status.
	 *
	 * @return string
	 */
	private static function check_database_connection(): string {
		global $wpdb;
		return $wpdb->check_connection() ? 'Connected' : 'Disconnected';
	}

	/**
	 * Example method to get total updates.
	 *
	 * @return int
	 */
	private static function get_total_updates(): int {
		return (int) get_option('wp2_total_updates', 0);
	}

	/**
	 * Example method to get successful updates.
	 *
	 * @return int
	 */
	private static function get_successful_updates(): int {
		return (int) get_option('wp2_successful_updates', 0);
	}

	/**
	 * Example method to get failed updates.
	 *
	 * @return int
	 */
	private static function get_failed_updates(): int {
		return (int) get_option('wp2_failed_updates', 0);
	}

	/**
	 * Determine the currently active tab slug.
	 */
	public static function get_active_tab(): string {
		$requested = '';
		if ( isset( $_GET['tab'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$requested = sanitize_key( (string) wp_unslash( $_GET['tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		return in_array( $requested, self::$allowed_tabs, true ) ? $requested : 'packages';
	}

	/**
	 * Fetch packages with optional release channel filtering.
	 *
	 * @param string|null $channel The release channel to filter by (e.g., stable, beta, nightly).
	 * @return array The filtered packages.
	 */
	public static function get_packages_by_channel(?string $channel = null): array {
		if (null === self::$package_service) {
			return [];
		}

		$packages = self::$package_service->get_managed_packages();

		if ($channel) {
			foreach ($packages as &$package) {
				$package['releases'] = array_filter(
					$package['releases'] ?? [],
					fn($release) => strpos($release['tag_name'], $channel) !== false
				);
			}
		}

		return $packages;
	}

	/**
	 * Aggregate data for multiple GitHub Apps.
	 *
	 * @return array Aggregated app data.
	 */
	public static function get_dashboard_data(): array {
		$apps = self::get_apps();
		$multiAppData = [];

		foreach ($apps as $app) {
			$multiAppData[] = [
				'id' => $app['id'],
				'name' => $app['name'],
				'status' => $app['status'],
				'package_count' => $app['package_count'],
				'managed_repositories' => $app['managed_repositories'],
			];
		}

		return $multiAppData;
	}
}
