<?php
namespace WP2\Update\Admin;

use WP2\Update\Data\AppData;
use WP2\Update\Data\PackageData;
use WP2\Update\Data\HealthData;
use WP2\Update\Services\Github\AppService;
use WP2\Update\Services\PackageService; // Import the PackageService class
use WP2\Update\Utils\Logger;
use WP2\Update\Config; // Import the Config class

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
     * Retrieve the complete preloaded state for the SPA bootstrap.
     *
     * @return array<string,mixed>
     */
    public function get_state(): array {
        return [
            'apps'              => $this->appData->getApps(),
            'packages'          => $this->packageData->get_all_packages_grouped(),
            'selectedAppId'     => $this->appData->find_active_app()['id'] ?? null,
            'connectionStatus'  => $this->appService->get_connection_status(),
            'health'            => $this->healthData->get_health_checks(),
            'stats'             => $this->get_stats_data(),
        ];
    }

    /**
     * Retrieve stats data for the dashboard. (Placeholder data)
     *
     * @return array<string, mixed>
     */
    public static function get_stats_data(): array {
        global $wpdb;

        error_log('AdminData::get_stats_data() called.');

        // Use the constant for the table name.
        $tableName = Config::LOGS_TABLE_NAME;

        // Check if the table exists before running queries.
        $tableExists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tableName));
        if (!$tableExists) {
            error_log("Table {$tableName} does not exist. Returning empty stats.");
            return [
                'totalUpdates' => 0,
                'successfulUpdates' => 0,
                'failedUpdates' => 0,
            ];
        }

        error_log("Table {$tableName} exists. Fetching stats.");
        $totalUpdates = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tableName}"));
        $successfulUpdates = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tableName} WHERE status = %s", 'success'));
        $failedUpdates = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tableName} WHERE status = %s", 'failure'));

        return [
            'totalUpdates' => $totalUpdates,
            'successfulUpdates' => $successfulUpdates,
            'failedUpdates' => $failedUpdates,
        ];
    }

    /**
     * Retrieve recent logs for the dashboard.
     *
     * @param int $limit The number of logs to retrieve.
     * @return array<string, mixed> The recent logs.
     */
    public static function get_recent_logs(int $limit = 10): array {
        global $wpdb;

        $tableName = Config::LOGS_TABLE_NAME;

        // Check if the table exists before running queries.
        $tableExists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tableName));
        if (!$tableExists) {
            error_log("Table {$tableName} does not exist. Returning empty logs.");
            return [];
        }

        // Fetch the most recent logs.
        $query = $wpdb->prepare(
            "SELECT * FROM {$tableName} ORDER BY created_at DESC LIMIT %d",
            $limit
        );
        $results = $wpdb->get_results($query, ARRAY_A);

        return $results ?: [];
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
            Logger::log('ERROR', 'Failed to retrieve grouped packages: ' . $e->getMessage());
            return [
                'managed'  => [],
                'unlinked' => [],
                'all'      => [],
                'error'    => __('Unable to load package information at this time.', \WP2\Update\Config::TEXT_DOMAIN),
            ];
        }
    }
}
