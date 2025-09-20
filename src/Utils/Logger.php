<?php
namespace WP2\Update\Core\Utils;

/**
 * A simple static logger utility for the plugin.
 *
 * Stores log entries in a WordPress option and automatically redacts secrets.
 */
class Logger {
	const LOG_OPTION_KEY  = 'wp2_update_log';
	const MAX_LOG_ENTRIES = 50;

	/**
	 * Logs a message with improved handling for arrays and objects.
	 *
	 * @param string|array|object $message The message to log.
	 * @param string $type    The type of log (e.g., 'info', 'error').
	 * @param string $context The context of the log (e.g., 'github-app', 'update-check').
	 * @param string $origin  The origin of the log (e.g., 'System', 'Package').
	 */
	public static function log( $message, $type = 'info', $context = 'general', $origin = 'System' ) {
		$logs = get_site_option( self::LOG_OPTION_KEY, [] );

		// Convert arrays and objects to JSON for logging
		if ( is_array( $message ) || is_object( $message ) ) {
			$message = json_encode( $message, JSON_PRETTY_PRINT );
		}

		$redacted = preg_replace(
			'/\b(?:token|Bearer)\s+[A-Za-z0-9._-]+|\bghp_[A-Za-z0-9]{30,}|\bgithub_pat_[A-Za-z0-9_]{20,}/i',
			'[REDACTED]',
			(string) $message
		);

		array_unshift( $logs, [ 
			'message'   => $redacted,
			'type'      => $type,
			'context'   => $context,
			'origin'    => $origin,
			'timestamp' => current_time( 'timestamp' ),
		] );

		update_site_option( self::LOG_OPTION_KEY, array_slice( $logs, 0, self::MAX_LOG_ENTRIES ) );
	}

	/**
	 * Retrieves all log entries.
	 *
	 * @return array The list of log entries.
	 */
	public static function get_logs(): array {
		return get_site_option( self::LOG_OPTION_KEY, [] );
	}
}
