<?php

namespace WP2\Update\REST;

use WP2\Update\REST\Controllers\CredentialsController;
use WP2\Update\REST\Controllers\ConnectionController;
use WP2\Update\REST\Controllers\PackagesController;

final class Router {
    private CredentialsController $credentialsController;
    private ConnectionController $connectionController;
    private PackagesController $packagesController;

    public function __construct(
        CredentialsController $credentialsController,
        ConnectionController $connectionController,
        PackagesController $packagesController
    ) {
        $this->credentialsController = $credentialsController;
        $this->connectionController = $connectionController;
        $this->packagesController = $packagesController;
    }

    public function register_routes(): void {
        register_rest_route('wp2-update/v1', '/save-credentials', [
            'methods'             => 'POST',
            'callback'            => [$this->credentialsController, 'rest_save_credentials'],
            'permission_callback' => [$this->credentialsController, 'check_permissions'],
        ]);

        register_rest_route('wp2-update/v1', '/connection-status', [
            'methods'             => 'GET',
            'callback'            => [$this->connectionController, 'get_connection_status'],
            'permission_callback' => [$this->connectionController, 'check_permissions'],
        ]);

        register_rest_route('wp2-update/v1', '/run-update-check', [
            'methods'             => 'POST',
            'callback'            => [$this->packagesController, 'rest_run_update_check'],
            'permission_callback' => [$this->packagesController, 'check_permissions'],
        ]);

        register_rest_route('wp2-update/v1', '/sync-packages', [
            'methods'             => 'GET',
            'callback'            => [$this->packagesController, 'sync_packages'],
            'permission_callback' => [$this->packagesController, 'check_permissions'],
        ]);

        register_rest_route('wp2-update/v1', '/manage-packages', [
            'methods'             => 'POST',
            'callback'            => [$this->packagesController, 'manage_packages'],
            'permission_callback' => [$this->packagesController, 'check_permissions'],
        ]);

        register_rest_route('wp2-update/v1', '/github/connect-url', [
            'methods'             => ['GET', 'POST'],
            'callback'            => [$this->credentialsController, 'rest_get_connect_url'],
            'permission_callback' => [$this->credentialsController, 'check_permissions'],
            'args'                => [
                'account_type' => [
                    'required' => true,
                    'type'     => 'string',
                    'enum'     => ['user', 'organization'],
                    'description' => 'The type of GitHub account (user or organization).',
                ],
                'organization' => [
                    'required' => false,
                    'type'     => 'string',
                    'description' => 'The slug of the organization (required if account_type is organization).',
                ],
                'manifest' => [
                    'required' => true,
                    'type'     => 'string',
                    'description' => 'The GitHub App manifest JSON.',
                ],
            ],
        ]);

        register_rest_route('wp2-update/v1', '/github/exchange-code', [
            'methods'             => 'POST',
            'callback'            => [$this->credentialsController, 'rest_exchange_code'],
            'permission_callback' => [$this->credentialsController, 'check_permissions'],
        ]);

        register_rest_route('wp2-update/v1', '/package/(?P<repo_slug>[\w-]+/[\w-]+)', [
            'methods'             => 'GET',
            'callback'            => [$this->packagesController, 'rest_get_package_status'],
            'permission_callback' => [$this->packagesController, 'check_permissions'],
        ]);

        register_rest_route('wp2-update/v1', '/health-status', [
            'methods'             => 'GET',
            'callback'            => [$this->connectionController, 'get_health_status'],
            'permission_callback' => [$this->connectionController, 'check_permissions'],
        ]);

        register_rest_route('wp2-update/v1', '/github/disconnect', [
            'methods'             => 'POST',
            'callback'            => [$this->credentialsController, 'rest_disconnect'],
            'permission_callback' => [$this->credentialsController, 'check_permissions'],
        ]);

        register_rest_route('wp2-update/v1', '/refresh-nonce', [
            'methods'             => 'GET',
            'callback'            => static function () {
                return rest_ensure_response([
                    'nonce' => wp_create_nonce('wp_rest'),
                ]);
            },
            'permission_callback' => static function () {
                return current_user_can('manage_options');
            },
        ]);
    }
}
