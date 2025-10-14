<?php

namespace WP2\Update\REST\Controllers;

use WP2\Update\REST\AbstractController;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Exposes a helper endpoint to refresh a nonce for a specific action.
 * This is useful for long-lived single-page applications.
 */
final class NonceController extends AbstractController {
    /**
     * Generates and returns a new nonce for a given action.
     */
    public function get_nonce(WP_REST_Request $request): WP_REST_Response {
        $action = $request->get_param('action');
        if (empty($action)) {
            return $this->respond(__('Nonce action is required.', \WP2\Update\Config::TEXT_DOMAIN), 400);
        }

        return $this->respond([
            'action' => $action,
            'nonce'  => wp_create_nonce($action),
        ]);
    }

    /**
     * Verifies a nonce for a given action.
     */
    public function verify_nonce(WP_REST_Request $request): WP_REST_Response {
        $action = $request->get_param('action');
        $nonce = $request->get_param('nonce');

        if (empty($action) || empty($nonce)) {
            return $this->respond(__('Action and nonce are required.', \WP2\Update\Config::TEXT_DOMAIN), 400);
        }

        if (!wp_verify_nonce($nonce, $action)) {
            return $this->respond(__('Invalid nonce.', \WP2\Update\Config::TEXT_DOMAIN), 403);
        }

        return $this->respond([ 'verified' => true ]);
    }

    /**
     * Registers the routes for this controller.
     */
    public function register_routes(): void {
        register_rest_route($this->namespace, '/nonce', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_nonce'],
            'permission_callback' => function () {
                // This endpoint is only available to logged-in users with admin capabilities.
                return current_user_can('manage_options');
            },
            'args' => [
                'action' => [
                    'description' => __('The nonce action for which to generate a token.', \WP2\Update\Config::TEXT_DOMAIN),
                    'type'        => 'string',
                    'required'    => true,
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);

        // Route to verify a nonce
        register_rest_route($this->namespace, '/nonce/verify', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'verify_nonce'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args' => [
                'action' => [
                    'description' => __('The nonce action to verify.', \WP2\Update\Config::TEXT_DOMAIN),
                    'type'        => 'string',
                    'required'    => true,
                    'sanitize_callback' => 'sanitize_key',
                ],
                'nonce' => [
                    'description' => __('The nonce to verify.', \WP2\Update\Config::TEXT_DOMAIN),
                    'type'        => 'string',
                    'required'    => true,
                ],
            ],
        ]);
    }
}
