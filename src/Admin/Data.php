<?php
namespace WP2\Update\Admin;

use WP2\Update\Data\AppData;
use WP2\Update\Data\PackageData;
use WP2\Update\Data\HealthData;
use WP2\Update\Health\Checks\DatabaseCheck;
use WP2\Update\Health\Checks\ConnectivityCheck;
use WP2\Update\Services\Github\AppService;
use WP2\Update\Services\Github\ClientService;
use WP2\Update\Services\Github\ReleaseService;
use WP2\Update\Services\Github\RepositoryService;
use WP2\Update\Services\PackageService;
use WP2\Update\Config;
use WP2\Update\Utils\Logger;

/**
 * Provides preloaded data for the admin dashboard SPA.
 * This centralizes data preparation to keep server-rendered markup and the JavaScript state in sync.
 */
final class Data {
    private AppData $appData;
    private PackageData $packageData;
    private HealthData $healthData;
    private AppService $appService;
    private array $allowedTabs = ['dashboard', 'packages', 'apps', 'health'];

    public function __construct(AppData $appData, PackageData $packageData, HealthData $healthData, AppService $appService) {
        $this->appData = $appData;
        $this->packageData = $packageData;
        $this->healthData = $healthData;
        $this->appService = $appService;
    }

    /**
     * Retrieve data for the active tab dynamically.
     *
     * @param string $tab The active tab slug.
     * @return array<string, mixed>
     */
    public function get_tab_data(string $tab): array {
        switch ($tab) {
            case 'packages':
                return [
                    'packages' => $this->packageData->get_all_packages_grouped(),
                ];
            case 'apps':
                return [
                    'apps' => $this->appData->getApps(),
                    'selectedAppId' => $this->appData->find_active_app()['id'] ?? null,
                ];
            case 'health':
                return [
                    'health' => $this->healthData->get_health_checks(),
                ];
            case 'dashboard':
            default:
                return [
                    'stats' => $this->get_stats_data(
                        $this->appData,
                        $this->packageData,
                        $this->healthData
                    ),
                    'recentLogs' => $this->get_recent_logs(),
                ];
        }
    }

    /**
     * Retrieve the complete preloaded state for the SPA bootstrap.
     *
     * @return array<string,mixed>
     */
    public function get_state(): array {
        $activeTab = $this->get_active_tab();
        $state = $this->get_tab_data($activeTab);

        Logger::debug('Bootstrapping frontend with initial state for tab: ' . $activeTab, ['state' => $state]);

        return $state;
    }

    /**
     * Retrieve stats data for the dashboard. (Placeholder data)
     *
     * @param AppData $appData
     * @param PackageData $packageData
     * @param HealthData $healthData
     * @return array<string, mixed>
     */
    public static function get_stats_data(AppData $appData, PackageData $packageData, HealthData $healthData): array {
        // Cache key for stats data
        $cache_key = 'wp2_update_stats_data';
        $cached_stats = get_transient($cache_key);

        if ($cached_stats !== false) {
            return $cached_stats;
        }

        // Retrieve app stats
        $totalApps = count($appData->all());

        // Retrieve package stats
        $packages = $packageData->get_all_packages_grouped();
        $totalPackages = count($packages['all'] ?? []);

        // Retrieve health stats
        $healthChecks = $healthData->get_health_checks();
        $totalHealthChecks = count($healthChecks);

        $stats = [
            'totalApps' => $totalApps,
            'totalPackages' => $totalPackages,
            'totalHealthChecks' => $totalHealthChecks,
        ];

        // Cache the stats data for 1 hour
        set_transient($cache_key, $stats, HOUR_IN_SECONDS);

        return $stats;
    }

    /**
     * Retrieve recent logs for the dashboard.
     *
     * @param int $limit The number of logs to retrieve.
     * @return array<string, mixed> The recent logs.
     */
    public static function get_recent_logs(int $limit = 10): array {
        $logs = get_option(Config::OPTION_LOGS, []);

        // Sort logs by timestamp in descending order
        usort($logs, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return array_slice($logs, 0, $limit);
    }

    /**
     * Determine the currently active tab slug from the URL query string.
     */
    public function get_active_tab(): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $requested = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : 'dashboard';

        return in_array($requested, $this->allowedTabs, true) ? $requested : 'dashboard';
    }

    /**
     * Retrieve the PackageService instance.
     *
     * @return PackageService
     */
    public function get_package_service(): PackageService {
        return $this->packageData->get_service();
    }

    /**
     * Retrieve the AppService instance.
     *
     * @return AppService
     */
    public function get_app_service(): AppService {
        return $this->appService;
    }

    /**
     * Retrieve all packages grouped by their status.
     *
     * @return array<string, mixed>
     */
    public function get_all_packages_grouped(): array {
        try {
            return $this->packageData->get_all_packages_grouped();
        } catch (\Throwable $e) {
            return [
                'managed'  => [],
                'unlinked' => [],
                'all'      => [],
                'error'    => __('Unable to load package information at this time.', Config::TEXT_DOMAIN),
            ];
        }
    }
}
