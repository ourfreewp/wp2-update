<?php
namespace WP2\Update\Utils;

/**
 * A simple static logger utility for the plugin.
 *
 * Stores log entries in a WordPress option and automatically redacts secrets.
 */
class Logger {
	const LOG_OPTION_KEY  = 'wp2_update_log';
	const MAX_LOG_ENTRIES = 100;

	/**
	 * Logs a message with improved handling for arrays and objects.
	 *
	 * @param string|array|object $message The message to log. If an array or object is provided, it will be JSON-encoded.
	 * @param string $type The type of log (e.g., 'info', 'error', 'success', 'debug'). Defaults to 'info'.
	 * @param string $context The context of the log (e.g., 'github-app', 'update-check'). Defaults to 'general'.
	 *
	 * @throws JsonException If the message cannot be JSON-encoded.
	 */
	public static function log( $message, $type = 'info', $context = 'general' ) {
		$logs = get_site_option( self::LOG_OPTION_KEY, [] );

		// Standardize the message format. If it's already structured, use it. Otherwise, wrap it.
        $log_message = is_array($message) || is_object($message) ? $message : ['message' => $message];

		// Convert the final message payload to a JSON string for storage.
		$message_string = json_encode( $log_message, JSON_PRETTY_PRINT );

		$redacted = preg_replace(
			'/\b(?:token|Bearer)\s+[A-Za-z0-9._-]+|\bghp_[A-Za-z0-9]{30,}|\bgithub_pat_[A-Za-z0-9_]{20,}/i',
			'[REDACTED]',
			(string) $message_string
		);

		array_unshift( $logs, [ 
			'message'   => $redacted,
			'type'      => $type,
			'context'   => $context,
			'timestamp' => current_time( 'timestamp' ),
		] );

		update_site_option( self::LOG_OPTION_KEY, array_slice( $logs, 0, self::MAX_LOG_ENTRIES ) );
	}

	/**
	 * Retrieves all log entries.
	 *
	 * @return array The list of log entries. Returns an empty array if no logs are found.
	 */
	public static function get_logs(): array {
		return get_site_option( self::LOG_OPTION_KEY, [] );
	}

    /**
     * Clears all log entries.
     *
     * @return void
     */
    public static function clear_logs(): void {
        delete_site_option(self::LOG_OPTION_KEY);
    }

	/**
	 * Logs a debug message when WP2_UPDATE_DEBUG is enabled.
	 *
	 * @param string|array|object $message The message to log. If an array or object is provided, it will be JSON-encoded.
	 * @param string $context The log context (e.g., 'api'). Defaults to 'general'.
	 *
	 * @throws JsonException If the message cannot be JSON-encoded.
	 */
	public static function log_debug( $message, string $context = 'general' ): void {
		if ( ! defined( 'WP2_UPDATE_DEBUG' ) || ! WP2_UPDATE_DEBUG ) {
			return;
		}

		self::log( $message, 'debug', $context );
	}

	/**
     * Retrieves log entries filtered by context.
     *
     * @param string $context The context to filter logs by.
     * @return array The filtered list of log entries. Returns an empty array if no logs match the context.
     */
    public static function get_logs_by_context(string $context): array {
        $logs = self::get_logs();
        return array_filter($logs, function ($log) use ($context) {
            return isset($log['context']) && $log['context'] === $context;
        });
    }

	/**
     * Logs a message with an associated package slug.
     *
     * @param string|array|object $message The message to log. If an array or object is provided, it will be JSON-encoded.
     * @param string $type The type of log (e.g., 'info', 'error', 'success', 'debug'). Defaults to 'info'.
     * @param string $context The context of the log (e.g., 'github-app', 'update-check'). Defaults to 'general'.
     * @param string|null $package_slug The slug of the package associated with the log entry. Optional.
     *
     * @throws JsonException If the message cannot be JSON-encoded.
     */
    public static function log_with_package($message, $type = 'info', $context = 'general', $package_slug = null) {
        $logs = get_site_option(self::LOG_OPTION_KEY, []);

        $log_message = is_array($message) || is_object($message) ? $message : ['message' => $message];
        $message_string = json_encode($log_message, JSON_PRETTY_PRINT);

        $redacted = preg_replace(
            '/\b(?:token|Bearer)\s+[A-Za-z0-9._-]+|\bghp_[A-Za-z0-9]{30,}|\bgithub_pat_[A-Za-z0-9_]{20,}/i',
            '[REDACTED]',
            (string) $message_string
        );

        $log_entry = [
            'message'   => $redacted,
            'type'      => $type,
            'context'   => $context,
            'timestamp' => current_time('timestamp'),
        ];

        if ($package_slug) {
            $log_entry['package_slug'] = $package_slug;
        }

        array_unshift($logs, $log_entry);
        update_site_option(self::LOG_OPTION_KEY, array_slice($logs, 0, self::MAX_LOG_ENTRIES));
    }

    /**
     * Logs an informational message.
     *
     * @param string $message The message to log.
     * @param string $context The context of the log.
     */
    public static function info(string $message, string $context = 'general'): void {
        self::log($message, 'info', $context);
    }

    /**
     * Logs an error message.
     *
     * @param string $message The message to log.
     * @param string $context The context of the log.
     */
    public static function error(string $message, string $context = 'general'): void {
        self::log($message, 'error', $context);
    }
}
