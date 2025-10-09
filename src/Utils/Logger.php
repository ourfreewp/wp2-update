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
        $timestamp = date('Y-m-d H:i:s');
        $level     = strtoupper($level);
        error_log("[WP2 Update] [{$timestamp}] [{$level}] {$message}");
    }
}
