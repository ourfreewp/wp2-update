<?php

namespace WP2\Update\Utils;

/**
 * Handles simple, standardized logging for the plugin.
 */
final class Logger
{
    /**
     * Logs a message to the PHP error log with a consistent format.
     *
     * @param string $level   The severity level (e.g., INFO, ERROR, SECURITY).
     * @param string $message The log message.
     */
    public static function log(string $level, string $message): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wp2_update_logs';

        // Fallback to error_log if the table doesn't exist yet
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            error_log("[WP2 Update] [{$level}] {$message}");
            return;
        }

        $wpdb->insert(
            $table_name,
            [
                'time'    => current_time('mysql'),
                'level'   => $level,
                'message' => $message,
            ]
        );
    }

    /**
     * Logs a message to the database and the PHP error log.
     *
     * @param string $level   The severity level (e.g., INFO, ERROR, SECURITY).
     * @param string $message The log message.
     * @param array  $context Additional context for the log entry.
     */
    public static function log_to_database(string $level, string $message, array $context = []): void
    {
        global $wpdb;

        // Log to the PHP error log
        $timestamp = date('Y-m-d H:i:s');
        $level     = strtoupper($level);
        error_log("[WP2 Update] [{$timestamp}] [{$level}] {$message}");

        // Log to the database
        $tableName = $wpdb->prefix . 'wp2_update_logs';
        $wpdb->insert(
            $tableName,
            [
                'timestamp' => current_time('mysql'),
                'level'     => $level,
                'message'   => $message,
                'context'   => json_encode($context),
            ],
            ['%s', '%s', '%s', '%s']
        );
    }

}
