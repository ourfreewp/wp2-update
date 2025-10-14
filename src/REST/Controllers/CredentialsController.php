<?php

namespace WP2\Update\REST\Controllers;

use WP2\Update\REST\AbstractController;
use WP2\Update\Services\Github\ConnectionService;
use WP2\Update\Utils\Logger;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Handles the GitHub App connection wizard, including manifest generation and code exchange.
 */
final class CredentialsController extends AbstractController {
    private ConnectionService $connectionService;

    public function __construct(ConnectionService $connectionService) {
        parent::__construct();
        $this->connectionService = $connectionService;
    }

    /**
     * Registers routes for the GitHub authentication flow.
     */
    public function register_routes(): void {
        register_rest_route($this->namespace, '/credentials/generate-manifest', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'generate_manifest'],
            'permission_callback' => $this->permission_callback('wp2_generate_manifest'),
            'args' => [
                'app_id' => [
                    'description' => __('The GitHub App ID.', \WP2\Update\Config::TEXT_DOMAIN),
                    'type'        => 'string',
                    'required'    => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($param) {
                        return preg_match('/^[a-zA-Z0-9_-]+$/', $param);
                    },
                ],
                'name' => [
                    'description' => __('The name of the GitHub App.', \WP2\Update\Config::TEXT_DOMAIN),
                    'type'        => 'string',
                    'required'    => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'account_type' => [
                    'description' => __('The account type (user or organization).', \WP2\Update\Config::TEXT_DOMAIN),
                    'type'        => 'string',
                    'required'    => false,
                    'sanitize_callback' => 'sanitize_key',
                ],
                'organization' => [
                    'description' => __('The organization slug.', \WP2\Update\Config::TEXT_DOMAIN),
                    'type'        => 'string',
                    'required'    => false,
                    'sanitize_callback' => 'sanitize_title',
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/credentials/exchange-code', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'exchange_code'],
            'permission_callback' => $this->permission_callback('wp2_exchange_code'),
        ]);

        register_rest_route($this->namespace, '/credentials/manual-setup', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'manual_setup'],
            'permission_callback' => $this->permission_callback('wp2_manual_setup'),
        ]);
    }

    /**
     * Generates the GitHub App manifest and a URL to create the app.
     */
    public function generate_manifest(WP_REST_Request $request): WP_REST_Response {
        $app_id = sanitize_text_field($request->get_param('app_id'));
        $name = sanitize_text_field($request->get_param('name'));
        $account_type = sanitize_key($request->get_param('account_type'));
        $org_slug = sanitize_title($request->get_param('organization'));

        if (empty($app_id) || empty($name)) {
            return $this->respond(__('App ID and name are required.', \WP2\Update\Config::TEXT_DOMAIN), 400);
        }

        try {
            $result = $this->connectionService->generate_manifest_data($app_id, $name, $account_type, $org_slug);
            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->respond($e->getMessage(), 500);
        }
    }

    /**
     * Exchanges a temporary GitHub code for permanent app credentials.
     */
    public function exchange_code(WP_REST_Request $request): WP_REST_Response {
        $code = sanitize_text_field($request->get_param('code'));
        $state = sanitize_text_field($request->get_param('state'));

        if (empty($code) || empty($state)) {
            return $this->respond(__('Invalid request. Code and state are required.', \WP2\Update\Config::TEXT_DOMAIN), 400);
        }

        try {
            $app = $this->connectionService->exchange_code_for_credentials($code, $state);
            return $this->respond($app);
        } catch (\Exception $e) {
            Logger::log('ERROR', 'GitHub code exchange failed: ' . $e->getMessage());
            return $this->respond($e->getMessage(), 400);
        }
    }

    /**
     * Handles manual entry of GitHub App credentials.
     */
    public function manual_setup(WP_REST_Request $request): WP_REST_Response {
        $app_id = sanitize_text_field($request->get_param('app_id'));
        $installation_id = sanitize_text_field($request->get_param('installation_id'));
        $private_key = sanitize_textarea_field($request->get_param('private_key'));

        if (empty($app_id) || empty($installation_id) || empty($private_key)) {
            return $this->respond(__('App ID, installation ID, and private key are required.', \WP2\Update\Config::TEXT_DOMAIN), 400);
        }

        try {
            $this->connectionService->store_manual_credentials($app_id, $installation_id, $private_key);
            return $this->respond(['message' => __('Credentials stored successfully.', \WP2\Update\Config::TEXT_DOMAIN)]);
        } catch (\Exception $e) {
            Logger::log('ERROR', 'Failed to store manual credentials: ' . $e->getMessage());
            return $this->respond($e->getMessage(), 500);
        }
    }
}
