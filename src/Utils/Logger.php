<?php
declare(strict_types=1);

namespace WP2\Update\Utils;
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

    // --- Core Logging Methods ---

    /**
     * Logs a message at the specified level dynamically.
     *
     * @param string $level   The PSR-3 log level.
     * @param mixed  $message The message or data to log.
     * @param array  $context Optional context for string interpolation.
     * @return void
     */
    public static function log(string $level, $message, array $context = []): void
    {
        if (!self::is_qm_active() || !defined('WP2_UPDATE_DEBUG') || !WP2_UPDATE_DEBUG) {
            return;
        }

        if (!in_array($level, self::VALID_LOG_LEVELS, true)) {
            $level = 'info';
        }

        // Dynamically call the appropriate Query Monitor method
        if (class_exists('QM') && method_exists('QM', $level)) {
            \QM::{$level}($message, $context);
        }
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
}