<?php

namespace WP2\Update\Utils;

/**
 * Handles logging for the plugin.
 */
class Logger
{
    /**
     * Logs messages with severity levels and timestamps.
     *
     * @param string $level The severity level (e.g., INFO, ERROR).
     * @param string $message The log message.
     */
    public static function log(string $level, string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        error_log("[WP2 Update] [{$timestamp}] [{$level}] {$message}");
    }
}