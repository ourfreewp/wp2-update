<?php
namespace WP2\Update\Admin;

use WP2\Update\Services\PackageService;
use WP2\Update\Services\Github\ConnectionService;
use WP2\Update\Utils\Logger;
use WP2\Update\Data\ConnectionData;

/**
 * Provides preloaded data for the admin dashboard SPA.
 * This centralizes data preparation to keep server-rendered markup and the JavaScript state in sync.
 */
final class Data {
    private PackageService $packageService;
    private ConnectionService $connectionService;
    private ConnectionData $appRepository;
    private array $allowedTabs = ['dashboard', 'packages', 'apps', 'health'];

    public function __construct(PackageService $packageService, ConnectionService $connectionService) {
        $this->packageService = $packageService;
        $this->connectionService = $connectionService;
        $this->appRepository = new ConnectionData();
    }

    /**
     * Retrieve the complete preloaded state for the SPA bootstrap.
     *
     * @return array<string,mixed>
     */
    public function get_state(): array {
        $apps     = $this->get_apps();
        $packages = $this->get_packages();

        return [
            'apps'              => $apps,
            'packages'          => $packages, // This now contains all groups
            'selectedAppId'     => $apps[0]['id'] ?? null,
            'connectionStatus'  => self::get_connection_status(),
            'health'            => self::get_health_data(),
            'stats'             => self::get_stats_data(),
        ];
    }

    /**
     * Retrieve and format all app definitions from storage.
     *
     * @return array<int,array<string,mixed>>
     */
    public function get_apps(): array {
        $raw_apps = $this->appRepository->all();

        return array_values(array_map(function ($app) {
            // Sanitize and format each app record for frontend consumption.
            $managed_repos = is_array($app['managed_repositories'] ?? []) ? $app['managed_repositories'] : [];
            return [
                'id'                   => sanitize_text_field($app['id'] ?? ''),
                'name'                 => sanitize_text_field($app['name'] ?? __('Unnamed App', \WP2\Update\Config::TEXT_DOMAIN)),
                'status'               => sanitize_key($app['status'] ?? 'pending'),
                'account_type'         => sanitize_key($app['account_type'] ?? 'user'),
                'package_count'        => count($managed_repos),
                'managed_repositories' => array_map('sanitize_text_field', $managed_repos),
                'created_at'           => sanitize_text_field($app['created_at'] ?? ''),
                'updated_at'           => sanitize_text_field($app['updated_at'] ?? ''),
                'installation_id'      => sanitize_text_field($app['installation_id'] ?? ''),
            ];
        }, $raw_apps));
    }

    /**
     * Retrieve and group all packages (managed, unlinked, etc.).
     *
     * @return array<string,mixed>
     */
    public function get_packages(): array {
        try {
            return $this->packageService->get_all_packages_grouped();
        } catch (\Throwable $e) {
            Logger::log('ERROR', 'Failed to retrieve packages for admin state: ' . $e->getMessage());
            return [
                'managed'  => [],
                'unlinked' => [],
                'all'      => [],
                'error'    => __('Unable to load package information at this time.', \WP2\Update\Config::TEXT_DOMAIN),
            ];
        }
    }

    /**
     * Retrieve the current GitHub connection status.
     *
     * @return array<string,mixed>
     */
    public static function get_connection_status(): array {
        // This method in ConnectionService is now responsible for determining the status.
        return self::$connectionService->get_connection_status();
    }

    /**
     * Retrieve health data for the dashboard.
     *
     * @return array<string, mixed>
     */
    public static function get_health_data(): array {
        global $wpdb;
        return [
            'phpVersion' => phpversion(),
            'dbStatus' => $wpdb->check_connection() ? 'Connected' : 'Disconnected',
            'activePlugins' => count(get_option('active_plugins', [])),
        ];
    }

    /**
     * Retrieve stats data for the dashboard. (Placeholder data)
     *
     * @return array<string, mixed>
     */
    public static function get_stats_data(): array {
        return [
            'totalUpdates' => (int) get_option('wp2_total_updates', 0),
            'successfulUpdates' => (int) get_option('wp2_successful_updates', 0),
            'failedUpdates' => (int) get_option('wp2_failed_updates', 0),
        ];
    }

    /**
     * Determine the currently active tab slug from the URL query string.
     */
    public function get_active_tab(): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $requested = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : 'dashboard';

        return in_array($requested, $this->allowedTabs, true) ? $requested : 'dashboard';
    }
}
