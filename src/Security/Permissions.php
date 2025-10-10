<?php

namespace WP2\Update\Security;

use WP_REST_Request;
use WP2\Update\Utils\Logger;

final class Permissions {
    public static function current_user_can_manage(?WP_REST_Request $request = null): bool {
        if (!current_user_can('manage_options')) {
            Logger::log('ERROR', 'Permission denied: manage_options.');
            return false;
        }

        if ($request instanceof WP_REST_Request) {
            $nonce = $request->get_header('X-WP-Nonce');
            if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
                Logger::log('ERROR', 'Permission denied: invalid nonce from header.');
                return false;
            }
        } else {
            $nonce = $_REQUEST['_wpnonce'] ?? '';
            if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
                Logger::log('ERROR', 'Permission denied: invalid nonce from request parameter.');
                return false;
            }
        }

        return true;
    }
}