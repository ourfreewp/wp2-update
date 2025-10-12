<?php

namespace WP2\Update\Core\Health;

class DataIntegrityCheck {

    private $wpdb;

    public function __construct($wpdb) {
        if (!isset($wpdb) || !property_exists($wpdb, 'prefix')) {
            throw new \RuntimeException('Invalid $wpdb instance provided.');
        }
        $this->wpdb = $wpdb;
    }

    public function check_logs_table($wpdb): array {
        if (!isset($wpdb) || !property_exists($wpdb, 'prefix')) {
            throw new \RuntimeException('Invalid $wpdb instance provided.');
        }

        $table_name = $wpdb->prefix . 'wp2_update_logs';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return ['logs' => ['No logs found. The log table has not been created yet.']];
        }

        $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY time DESC LIMIT 100", ARRAY_A);

        return ['logs' => $logs];
    }
}