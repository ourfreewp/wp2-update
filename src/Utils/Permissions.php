<?php

namespace WP2\Update\Utils;

use WP_REST_Request;

/**
 * Utility class for handling permissions and security checks.
 */
final class Permissions {

    /**
     * A comprehensive permission check for REST endpoints.
     *
     * Verifies that the current user has 'manage_options' capability and that
     * a valid nonce for the specified action is present in the request header.
     *
     * @param string $action The nonce action to verify.
     * @param WP_REST_Request $request The REST request object.
     * @return bool True if the user has permission, false otherwise.
     */
    public static function current_user_can_manage(string $action, WP_REST_Request $request): bool {
        // 1. Check user capability.
        if (!current_user_can('manage_options')) {
            return false;
        }

        // 2. Check for a valid nonce for the specific action.
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, $action)) {
            // Log the failure for security auditing if needed.
            Logger::log('SECURITY', "Invalid nonce provided for action: {$action}");
            return false;
        }

        return true;
    }
}
