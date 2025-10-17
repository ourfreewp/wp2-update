<?php
declare(strict_types=1);

namespace WP2\Update\Health\Checks;

use WP2\Update\Health\AbstractCheck;
use WP2\Update\Utils\Logger;

/**
 * Health check for verifying data integrity, such as database tables.
 */
class DataIntegrityCheck extends AbstractCheck {

    protected string $id = 'data_integrity';

    protected string $label = 'Data Integrity';

    public function __construct() {
        parent::__construct('data_integrity_check');
    }

    // return directly
    
    function run(): array {
        // Log the start of the health check
        Logger::info('Starting DataIntegrityCheck health check.');

        $issues = [];

        // Check for orphaned records
        $orphanedRecords = $this->checkForOrphanedRecords();
        if (!empty($orphanedRecords)) {
            $issues[] = 'Found orphaned records in the database: ' . implode(", ", $orphanedRecords);
        }

        // Check for missing tables
        $missingTables = $this->checkForMissingTables();
        if (!empty($missingTables)) {
            $issues[] = 'Missing database tables: ' . implode(", ", $missingTables);
        }

        if (empty($issues)) {
            Logger::info('DataIntegrityCheck health check passed.');
            return [
                'label' => $this->label,
                'status' => 'ok',
                'message' => 'Data integrity check passed.',
            ];
        }

        Logger::error('DataIntegrityCheck health check failed.', ['issues' => $issues]);
        return [
            'label' => $this->label,
            'status' => 'critical',
            'message' => 'Data integrity issues found: ' . implode("; ", $issues),
        ];
    }

    private function checkForOrphanedRecords(): array {
        $orphanedRecords = [];

        $allOptions = wp_load_alloptions();
        foreach ($allOptions as $key => $value) {
            if (strpos($key, 'wp2_update_') === 0 && empty($value)) {
                $orphanedRecords[] = $key;
            }
        }

        return $orphanedRecords;
    }

    private function checkForMissingTables(): array {
        global $wpdb;
        $requiredTables = [
            $wpdb->prefix . 'wp2_update_logs',
            $wpdb->prefix . 'wp2_update_packages',
        ];

        $missingTables = [];
        foreach ($requiredTables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                $missingTables[] = $table;
            }
        }

        return $missingTables;
    }

}
