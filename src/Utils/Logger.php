<?php
declare(strict_types=1);

namespace WP2\Update\Utils;

defined('ABSPATH') || exit;

use WP2\Update\Config;
/**
 * Class Logger
 *
 * A full-spectrum static logger class for the Query Monitor plugin.
 *
 * Provides a clear and simple API for logging messages, profiling code performance,
 * and making assertions. All methods are safe to use even if Query Monitor
 * is not active.
 */
final class Logger
{
    /**
     * A list of valid PSR-3 log levels that Query Monitor supports.
     *
     * @var string[]
     */
    private const VALID_LOG_LEVELS = [
        'debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'
    ];

    /**
     * Checks if Query Monitor is active and its API is available.
     *
     * @return bool True if QM is ready, false otherwise.
     */
    private static function is_qm_active(): bool
    {
        return class_exists('QM');
    }

    /**
     * Holds global context for all log messages.
     *
     * @var array
     */
    private static array $globalContext = [];

    // --- Core Logging Methods ---

    /**
     * Sets global context for all log messages.
     *
     * @param array $context Global context to merge with log-specific context.
     */
    public static function setGlobalContext(array $context): void
    {
        self::$globalContext = $context;
    }

    /**
     * Persists a log message to the database.
     *
     * @param string $level   The log level (e.g., 'info', 'error').
     * @param string $message The log message.
     * @param array  $context Optional context for the log.
     */
    private static function persist_to_db(string $level, string $message, array $context = []): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wp2_update_logs';
        $contextJson = json_encode($context);

        $wpdb->insert(
            $table,
            [
                'level'   => $level,
                'message' => $message,
                'context' => $contextJson,
                'created_at' => current_time('mysql', 1),
            ],
            ['%s', '%s', '%s', '%s']
        );
    }

    /**
     * Rotates the logs by deleting old entries beyond a certain threshold.
     *
     * @param int $maxEntries The maximum number of log entries to retain.
     */
    public static function rotate_logs(int $maxEntries = 1000): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wp2_update_logs';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table WHERE id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM $table ORDER BY created_at DESC LIMIT %d
                    ) as subquery
                )",
                $maxEntries
            )
        );
    }

    /**
     * Logs a message at the specified level dynamically, with optional global context.
     *
     * @param string $level   The PSR-3 log level.
     * @param mixed  $message The message or data to log.
     * @param array  $context Optional context for string interpolation.
     * @return void
     */
    public static function log(string $level, $message, array $context = []): void
    {
        if (!in_array($level, self::VALID_LOG_LEVELS, true)) {
            $level = 'info';
        }

        $mergedContext = array_merge(self::$globalContext, $context);
        $formattedMessage = sprintf('[%s] %s: %s', strtoupper($level), 'WP2 Update', is_string($message) ? $message : json_encode($message));

        if (self::is_qm_active() && defined('WP2_UPDATE_DEBUG') && WP2_UPDATE_DEBUG) {
            if (class_exists('QM') && method_exists('QM', $level)) {
                \QM::$level($formattedMessage, $mergedContext);
            }
        }

        error_log($formattedMessage);
        self::persist_to_db($level, $formattedMessage, $mergedContext);
    }

    /** Logs a debug message. */
    public static function debug($message, array $context = []): void { self::log('debug', $message, $context); }

    /** Logs an info message. */
    public static function info($message, array $context = []): void { self::log('info', $message, $context); }
    
    /** Logs a notice. */
    public static function notice($message, array $context = []): void { self::log('notice', $message, $context); }

    /** Logs a warning message. */
    public static function warning($message, array $context = []): void { self::log('warning', $message, $context); }

    /** Logs an error message. */
    public static function error($message, array $context = []): void { self::log('error', $message, $context); }
    
    /** Logs a critical message. */
    public static function critical($message, array $context = []): void { self::log('critical', $message, $context); }

    // --- Profiling Methods ---

    /**
     * Starts a Query Monitor timer.
     *
     * @param string $timerName The unique name for the timer.
     */
    public static function start(string $timerName): void
    {
        if (self::is_qm_active()) {
            do_action('qm/start', $timerName);
        }
    }

    /**
     * Stops a Query Monitor timer.
     *
     * @param string $timerName The name of the timer to stop.
     */
    public static function stop(string $timerName): void
    {
        if (self::is_qm_active()) {
            do_action('qm/stop', $timerName);
        }
    }

    /**
     * Records a lap for an active Query Monitor timer.
     *
     * @param string $timerName The name of the timer to record a lap for.
     */
    public static function lap(string $timerName): void
    {
        if (self::is_qm_active()) {
            do_action('qm/lap', $timerName);
        }
    }

    // --- Assertion Method ---

    /**
     * Performs an assertion, logging an error in Query Monitor if it fails.
     *
     * @param mixed       $condition    The condition or expression to assert (should evaluate to bool).
     * @param string      $description  A message describing the assertion.
     * @param mixed|null  $value        An optional value to log if the assertion fails.
     */
    public static function assert($condition, string $description = 'Assertion failed', $value = null): void
    {
        if (!self::is_qm_active()) {
            return;
        }

        if (class_exists('QM')) {
            \QM::assert($condition, $description, $value);
        }
    }

    // Add correlation ID to global context
    public static function setCorrelationId(string $correlationId): void
    {
        self::$globalContext['correlation_id'] = $correlationId;
    }

    // Ensure this logic is placed inside a method or function
    public static function initializeCorrelationId(): void
    {
        if (isset($_SERVER['HTTP_X_CORRELATION_ID'])) {
            self::setCorrelationId($_SERVER['HTTP_X_CORRELATION_ID']);
        }
    }

    /**
     * Retrieves recent logs for streaming from the options API.
     *
     * @return array The recent logs.
     */
    public static function get_recent_logs(): array {
        $logs = get_option('wp2_update_logs', []);
        return is_array($logs) ? $logs : [];
    }

    /**
     * Adds a log entry to the options API.
     *
     * @param string $message The log message.
     * @param array $context Additional context for the log entry.
     */
    public static function add_log(string $message, array $context = []): void {
        $logs = get_option('wp2_update_logs', []);

        if (!is_array($logs)) {
            $logs = [];
        }

        $logs[] = [
            'message' => $message,
            'context' => $context,
            'timestamp' => current_time('mysql'),
        ];

        // Keep only the most recent 50 logs
        if (count($logs) > 50) {
            $logs = array_slice($logs, -50);
        }

        update_option('wp2_update_logs', $logs);
    }
}