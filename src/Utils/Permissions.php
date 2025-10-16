<?php

namespace WP2\Update\Utils;

use WP_REST_Request;
use WP2\Update\Utils\Logger;
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
        // 1. Check user capability.
        if (!current_user_can('manage_options')) {
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
        $nonce = $request->get_header('X-WP-Nonce') ?: $request->get_param('_wpnonce');
        $isValid = $nonce && wp_verify_nonce($nonce, $action);

        if (!$isValid) {
            Logger::error('Nonce validation failed.', ['action' => $action, 'nonce' => $nonce]);
        } else {
            Logger::info('Nonce validation succeeded.', ['action' => $action, 'nonce' => $nonce]);
        }

        return $isValid;
    }

    /**
     * Generates a permission callback for REST routes.
     *
     * @param string $capability The capability required to access the route.
     * @param bool $requireNonce Whether to validate a nonce.
     * @return callable The permission callback.
     */
    public static function callback(string $capability, bool $requireNonce = false): callable
    {
        return function () use ($capability, $requireNonce) {
            if (!current_user_can($capability)) {
                return new WP_Error(
                    'rest_forbidden',
                    __('You do not have permission to perform this action.', 'wp2-update'),
                    ['status' => 403]
                );
            }

            if ($requireNonce) {
                $nonce = $_REQUEST['_wpnonce'] ?? '';
                if (!wp_verify_nonce($nonce, 'wp_rest')) {
                    return new WP_Error(
                        'rest_invalid_nonce',
                        __('Invalid nonce.', 'wp2-update'),
                        ['status' => 403]
                    );
                }
            }

            return true;
        };
    }
}
