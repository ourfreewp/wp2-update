<?php
namespace WP2\Update\Utils;

class Log {
    const LOG_OPTION_KEY = 'wp2_update_log';
    const MAX_LOG_ENTRIES = 10;

    public static function add($message, $type = 'info', $context = 'general') {
        $logs = get_site_option(self::LOG_OPTION_KEY, []);
        $redacted = preg_replace(
            '/(token\s+[A-Za-z0-9._-]+|Bearer\s+[A-Za-z0-9._-]+)/i',
            '[REDACTED]',
            $message
        );
        array_unshift($logs, [
            'message' => $redacted,
            'type' => $type,
            'context' => $context,
            'timestamp' => current_time('timestamp'),
        ]);
        $logs = array_slice($logs, 0, self::MAX_LOG_ENTRIES);
        update_site_option(self::LOG_OPTION_KEY, $logs);
    }

    /**
     * Get all logs, optionally filtered by type.
     * @param string|null $type
     * @return array
     */
    public static function get_logs($type = null) {
        $logs = get_site_option(self::LOG_OPTION_KEY, []);
        if ($type) {
            return array_filter($logs, function($log) use ($type) {
                return $log['type'] === $type;
            });
        }
        return $logs;
    }

    /**
     * Export logs as JSON for download/debugging.
     * @return string
     */
    public static function export_logs() {
        $logs = get_site_option(self::LOG_OPTION_KEY, []);
        return json_encode($logs, JSON_PRETTY_PRINT);
    }
}