<?php
namespace WP2\Update\Utils;

use WP_Error;

/**
 * Centralized error handling utility class.
 */
class ErrorHandler {

    /**
     * Handles errors by logging and setting admin notices.
     *
     * @param WP_Error $error The error object to handle.
     */
    public static function handle_error(WP_Error $error): void {
        if (is_wp_error($error)) {
            // Log the error.
            Logger::log($error->get_error_message(), 'error', 'general');

            // Set an admin notice transient.
            set_transient('wp2_admin_notice', $error->get_error_message(), 30);
        }
    }

    /**
     * Displays admin notices for errors.
     */
    public static function display_admin_notice(): void {
        $notice = get_transient('wp2_admin_notice');
        if ($notice) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($notice) . '</p></div>';
            delete_transient('wp2_admin_notice');
        }
    }
}

// Hook to display admin notices.
add_action('admin_notices', [ErrorHandler::class, 'display_admin_notice']);