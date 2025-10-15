<?php

namespace WP2\Update\REST\Controllers;

use WP2\Update\REST\AbstractController;
use WP2\Update\Services\PackageService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * REST controller for all package-related actions.
 */
final class PackagesController extends AbstractController {
    private PackageService $packageService;

    public function __construct(PackageService $packageService) {
        parent::__construct();
        $this->packageService = $packageService;
    }

    /**
     * Verifies the nonce for a given action.
     */
    private function verify_action_nonce(WP_REST_Request $request, string $action): bool {
        $nonce = $request->get_header('X-WP-Nonce');
        return wp_verify_nonce($nonce, $action);
    }

    /**
     * Validates the nonce for REST requests.
     *
     * @return bool
     */
    private function validate_nonce(): bool {
        $nonce = $_REQUEST['_wpnonce'] ?? '';
        return wp_verify_nonce($nonce, 'wp_rest') !== false;
    }

    /**
     * Wrapper for permission callbacks with nonce validation.
     *
     * @param callable $callback The original permission callback.
     * @return callable
     */
    private function permission_callback_with_nonce(callable $callback): callable {
        return function () use ($callback) {
            if (!$this->validate_nonce()) {
                return new WP_Error('rest_nonce_invalid', __('Invalid nonce.', 'wp2-update'), ['status' => 403]);
            }
            return call_user_func($callback);
        };
    }

    /**
     * Registers routes for package management.
     */
    public function register_routes(): void {
        // Route to get all packages
        register_rest_route($this->namespace, '/packages', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_packages'],
            'permission_callback' => $this->permission_callback_with_nonce($this->permission_callback('wp2_get_packages')),
        ]);

        // Route to force a sync
        register_rest_route($this->namespace, '/packages/sync', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'sync_packages'],
            'permission_callback' => $this->permission_callback_with_nonce($this->permission_callback('wp2_sync_packages')),
        ]);

        // Route to assign a package to an app
        register_rest_route($this->namespace, '/packages/assign', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'assign_package'],
            'permission_callback' => $this->permission_callback('wp2_assign_package'),
        ]);

        // Route to perform an action on a package (update/rollback)
        register_rest_route($this->namespace, '/packages/action', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_package_action'],
            'permission_callback' => $this->permission_callback('wp2_package_action'),
        ]);

        // Route to get release notes for a specific package
        register_rest_route($this->namespace, '/packages/(?P<repo_slug>[^/]+)/release-notes', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_release_notes'],
            'permission_callback' => $this->permission_callback('wp2_get_release_notes'),
        ]);

        // Route to update the release channel for a specific package
        register_rest_route($this->namespace, '/packages/(?P<repo_slug>[^/]+)/release-channel', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'update_release_channel'],
            'permission_callback' => $this->permission_callback('wp2_update_release_channel'),
        ]);

        // Route to create a new package
        register_rest_route($this->namespace, '/packages/create', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'create_package'],
            'permission_callback' => $this->permission_callback('wp2_create_package'),
        ]);

        // Route to refresh packages
        register_rest_route($this->namespace, '/refresh-packages', [
            'methods'  => 'POST',
            'callback' => [$this, 'refresh_packages'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    /**
     * Retrieves paginated packages.
     */
    public function get_packages(WP_REST_Request $request): WP_REST_Response {
        $page = max(1, (int) $request->get_param('page'));
        $per_page = max(1, min(100, (int) $request->get_param('per_page')));

        try {
            $packages = $this->packageService->get_paginated_packages($page, $per_page);
            return $this->respond($packages);
        } catch (\Exception $e) {
            return $this->respond($e->getMessage(), 500);
        }
    }

    /**
     * Triggers a package synchronization.
     */
    public function sync_packages(WP_REST_Request $request): WP_REST_Response {
        try {
            $result = $this->packageService->get_all_packages_grouped();
            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->respond($e->getMessage(), 500);
        }
    }

    /**
     * Assigns a package (repository) to be managed by a specific app.
     */
    public function assign_package(WP_REST_Request $request): WP_REST_Response {
        $app_id = sanitize_text_field($request->get_param('app_id'));
        $repo_slug = sanitize_text_field($request->get_param('repo_slug'));

        if (empty($app_id) || empty($repo_slug)) {
            return $this->respond(__("App ID and repository slug are required.", \WP2\Update\Config::TEXT_DOMAIN), 400);
        }

        try {
            $this->packageService->assign_package_to_app($app_id, $repo_slug);
            return $this->respond(["message" => __("Package assigned successfully.", \WP2\Update\Config::TEXT_DOMAIN)]);
        } catch (\Exception $e) {
            return $this->respond($e->getMessage(), 500);
        }
    }

    /**
     * Handles package actions like 'update' and 'rollback'.
     */
    public function handle_package_action(WP_REST_Request $request): WP_REST_Response {
        $action = sanitize_key($request->get_param('action'));
        $repo_slug = sanitize_text_field($request->get_param('repo_slug'));
        $version = sanitize_text_field($request->get_param('version'));

        if (empty($action) || empty($repo_slug)) {
            return $this->respond(__("Action and repository slug are required.", \WP2\Update\Config::TEXT_DOMAIN), 400);
        }

        try {
            switch ($action) {
                case 'update':
                    $success = $this->packageService->update_package($repo_slug);
                    $message = $success ? __("Package updated successfully.", \WP2\Update\Config::TEXT_DOMAIN) : __("Failed to update package.", \WP2\Update\Config::TEXT_DOMAIN);
                    return $this->respond(['message' => $message], $success ? 200 : 500);

                case 'rollback':
                    if (empty($version)) {
                        return $this->respond(__("Version is required for rollback.", \WP2\Update\Config::TEXT_DOMAIN), 400);
                    }
                    $success = $this->packageService->rollback_package($repo_slug, $version);
                    $message = $success ? __("Package rolled back successfully.", \WP2\Update\Config::TEXT_DOMAIN) : __("Failed to roll back package.", \WP2\Update\Config::TEXT_DOMAIN);
                    return $this->respond(['message' => $message], $success ? 200 : 500);

                default:
                    return $this->respond(__("Invalid action specified.", \WP2\Update\Config::TEXT_DOMAIN), 400);
            }
        } catch (\Exception $e) {
            return $this->respond($e->getMessage(), 500);
        }
    }

    /**
     * Retrieves release notes for a specific package.
     */
    public function get_release_notes(WP_REST_Request $request): WP_REST_Response {
        $repo_slug = sanitize_text_field($request->get_param('repo_slug'));

        if (empty($repo_slug)) {
            return $this->respond(__("Repository slug is required.", \WP2\Update\Config::TEXT_DOMAIN), 400);
        }

        try {
            $release_notes = $this->packageService->get_release_notes($repo_slug);
            return $this->respond($release_notes);
        } catch (\Exception $e) {
            return $this->respond($e->getMessage(), 500);
        }
    }

    /**
     * Updates the release channel for a specific package.
     */
    public function update_release_channel(WP_REST_Request $request): WP_REST_Response {
        $repo_slug = sanitize_text_field($request->get_param('repo_slug'));
        $channel = sanitize_text_field($request->get_param('channel'));

        if (empty($repo_slug) || empty($channel)) {
            return $this->respond(__("Repository slug and channel are required.", \WP2\Update\Config::TEXT_DOMAIN), 400);
        }

        try {
            $this->packageService->update_release_channel($repo_slug, $channel);
            return $this->respond(["message" => __("Release channel updated successfully.", \WP2\Update\Config::TEXT_DOMAIN)]);
        } catch (\Exception $e) {
            return $this->respond($e->getMessage(), 500);
        }
    }

    /**
     * Handles the creation of a new package.
     */
    public function create_package(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params();
        $template = $params['template'] ?? '';
        $name = $params['name'] ?? '';
        $app_id = $params['app_id'] ?? '';

        if (empty($template) || empty($name) || empty($app_id)) {
            return $this->respond(__('Invalid parameters. The app_id is required.', \WP2\Update\Config::TEXT_DOMAIN), 400);
        }

        try {
            $result = $this->packageService->create_new_package($template, $name, $app_id); // Pass app_id explicitly
            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->respond($e->getMessage(), 500);
        }
    }

    /**
     * Refreshes the package data by scanning for plugins and themes.
     */
    public function refresh_packages(): WP_REST_Response {
        try {
            $this->packageService->scan_for_packages();

            return new WP_REST_Response([
                'success' => true,
                'message' => __('Packages refreshed successfully.', \WP2\Update\Config::TEXT_DOMAIN),
            ], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retrieves all packages, including unlinked ones.
     */
    public function get_all_packages(WP_REST_Request $request): WP_REST_Response {
        try {
            $packages = $this->packageService->get_all_packages();
            return $this->respond($packages);
        } catch (\Exception $e) {
            return $this->respond($e->getMessage(), 500);
        }
    }
}
