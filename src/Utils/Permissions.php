<?php
declare(strict_types=1);

namespace WP2\Update\Utils;

defined('ABSPATH') || exit;

use WP_REST_Request;
use WP2\Update\Utils\Logger;
use WP2\Update\Config;
use WP_Error;

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
        if (defined('WP2_UPDATE_DEBUG') && WP2_UPDATE_DEBUG) {
            Logger::debug('Checking if current user can manage.', ['action' => $action, 'user_id' => get_current_user_id()]);
        }
        // 1. Check user capability.
        if (!current_user_can(Config::CAPABILITY)) {
            Logger::warning('Permission denied: User lacks manage_options capability.', ['action' => $action]);
            return false;
        }

        // 2. Validate nonce using the new validate_nonce method.
        if (!self::validate_nonce($action, $request)) {
            Logger::warning('Permission denied: Invalid nonce.', ['action' => $action]);
            return false;
        }

        Logger::info('Permission granted.', ['action' => $action]);
        return true;
    }

    /**
     * Validates a nonce from both the `X-WP-Nonce` header and the `_wpnonce` URL parameter.
     *
     * @param string $action The nonce action to verify.
     * @param WP_REST_Request $request The REST request object.
     * @return bool True if the nonce is valid, false otherwise.
     */
    private static function validate_nonce(string $action, WP_REST_Request $request): bool {
        if (defined('WP2_UPDATE_DEBUG') && WP2_UPDATE_DEBUG) {
            Logger::debug('Validating nonce.', ['action' => $action]);
        }
        $nonce = $request->get_header('X-WP-Nonce') ?: $request->get_param('_wpnonce');
        if (!$nonce) {
            Logger::error('Nonce is missing.', ['action' => $action]);
            return false;
        }

        $user_id = get_current_user_id();
        $expected_action = 'wp_rest'; // Use a consistent action for REST requests
        $isValid = wp_verify_nonce($nonce, $expected_action);

        if (!$isValid) {
            Logger::error('Nonce validation failed.', ['action' => $action, 'nonce' => $nonce]);
        } else {
            Logger::info('Nonce validation succeeded.', ['action' => $action, 'nonce' => $nonce]);
        }

        return $isValid;
    }



    /**
     * Generates a permission callback for REST routes with optional nonce validation.
     *
     * @param string $capability The capability required to access the route.
     * @param string|null $nonce_action The nonce action to verify (optional).
     * @return callable The permission callback.
     */
    public static function callback(string $capability, ?string $nonce_action = null): callable
    {
        return function (WP_REST_Request $request) use ($capability, $nonce_action) {
            // Check user capability
            if (!current_user_can($capability)) {
                Logger::warning('Permission denied: User lacks required capability.', ['capability' => $capability]);
                return false;
            }

            // Validate nonce if action is provided
            if ($nonce_action && !self::validate_nonce($nonce_action, $request)) {
                Logger::warning('Permission denied: Invalid nonce.', ['nonce_action' => $nonce_action]);
                return false;
            }

            Logger::info('Permission granted.', ['capability' => $capability, 'nonce_action' => $nonce_action]);
            return true;
        };
    }
}
