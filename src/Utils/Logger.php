<?php

namespace WP2\Update\Utils;

use WP2\Update\Config;

/**
 * Handles standardized logging to a custom database table.
 */
final class Logger
{
    /**
     * Logs a message to the custom database table.
     *
     * @param string $level   The severity level (e.g., 'INFO', 'ERROR', 'DEBUG').
     * @param string $message The log message.
     * @param array  $context Optional additional data to log as a JSON string.
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . Config::LOGS_TABLE_NAME;

        // Fallback to error_log if the table doesn't exist, to prevent fatal errors during activation.
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[WP2 Update Fallback] [{$level}] {$message}");
            }
            return;
        }

        $wpdb->insert(
            $table_name,
            [
                'timestamp' => current_time('mysql', true),
                'level'     => strtoupper($level),
                'message'   => $message,
                'context'   => !empty($context) ? wp_json_encode($context) : null,
            ],
            ['%s', '%s', '%s', '%s']
        );
    }

    /**
     * Retrieves logs from the database with pagination.
     *
     * @param int $limit The number of logs to retrieve.
     * @param int $offset The starting point for retrieval.
     * @return array The list of log entries.
     */
    public static function get_logs(int $limit, int $offset): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . Config::LOGS_TABLE_NAME;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} ORDER BY id DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );
    }

    /**
     * Fetches recent logs, optionally filtering by last ID.
     *
     * @param int|null $lastId The last log ID received by the client.
     * @return array The recent logs.
     */
    public static function get_recent_logs(?int $lastId = null): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . Config::LOGS_TABLE_NAME;

        if ($lastId) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id > %d ORDER BY id ASC LIMIT %d",
                $lastId,
                10
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table_name} ORDER BY id DESC LIMIT %d",
                10
            );
        }

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Prunes logs older than a specified retention period.
     */
    public static function prune_logs(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . Config::LOGS_TABLE_NAME;

        // Define retention period (e.g., 30 days)
        $retention_period = apply_filters('wp2_update_log_retention_days', 30);
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $retention_period
            )
        );
    }
}
