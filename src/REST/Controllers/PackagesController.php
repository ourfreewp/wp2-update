<?php
declare(strict_types=1);

namespace WP2\Update\REST\Controllers;

defined('ABSPATH') || exit;

use WP2\Update\REST\AbstractController;
use WP2\Update\Services\PackageService;
use WP2\Update\Utils\Permissions;
use WP2\Update\Utils\Logger;
use WP2\Update\Utils\CustomException;
use WP2\Update\Config;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class PackagesController
 *
 * This class handles REST API endpoints for managing package-related actions.
 */
final class PackagesController extends AbstractController {
    /**
     * @var PackageService The service responsible for handling package operations.
     */
    private PackageService $packageService;

    /**
     * Constructor for the PackagesController class.
     *
     * @param PackageService $packageService The service responsible for handling package operations.
     */
    public function __construct(PackageService $packageService) {
        parent::__construct();
        $this->packageService = $packageService;
    }

    /**
     * Verifies the nonce for a given action.
     *
     * @param WP_REST_Request $request The REST request object.
     * @param string $action The action name for nonce verification.
     * @return bool True if the nonce is valid, false otherwise.
     */
    private function verify_action_nonce(WP_REST_Request $request, string $action): bool {
        $nonce = $request->get_header('X-WP-Nonce');
        return wp_verify_nonce($nonce, 'wp2_update_action');
    }

    /**
     * Registers routes for package management.
     */
    public function register_routes(): void {
        register_rest_route(Config::REST_NAMESPACE, '/packages', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_packages'],
            'permission_callback' => Permissions::callback(Config::CAP_MANAGE, 'wp_rest'),
        ]);

        register_rest_route(Config::REST_NAMESPACE, '/packages/sync', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'sync_packages'],
            'permission_callback' => Permissions::callback(Config::CAP_MANAGE, 'wp_rest'),
        ]);

        register_rest_route(Config::REST_NAMESPACE, '/packages/assign', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'assign_package'],
            'permission_callback' => Permissions::callback(Config::CAP_MANAGE, 'wp_rest'),
        ]);

        register_rest_route(Config::REST_NAMESPACE, '/packages/(?P<repo_slug>[a-zA-Z0-9_-]+/[a-zA-Z0-9_-]+)/update', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'update_package'],
            'permission_callback' => Permissions::callback(Config::CAP_MANAGE, 'wp_rest'),
        ]);

        register_rest_route(Config::REST_NAMESPACE, '/packages/(?P<repo_slug>[a-zA-Z0-9_-]+/[a-zA-Z0-9_-]+)/rollback', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => Permissions::callback('manage_options'),
        ]);

        // Route to get release notes for a specific package
        register_rest_route(Config::REST_NAMESPACE, '/packages/(?P<repo_slug>[^/]+)/release-notes', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_release_notes'],
            'permission_callback' => Permissions::callback(Config::CAP_MANAGE, 'wp_rest'),
        ]);

        // Route to update the release channel for a specific package
        register_rest_route(Config::REST_NAMESPACE, '/packages/(?P<repo_slug>[a-zA-Z0-9_-]+/[a-zA-Z0-9_-]+)/release-channel', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'update_release_channel'],
            'permission_callback' => Permissions::callback(Config::CAP_MANAGE, 'wp_rest'),
        ]);

        // Route to create a new package
        register_rest_route(Config::REST_NAMESPACE, '/packages/create', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'create_package'],
            'permission_callback' => Permissions::callback(Config::CAP_MANAGE, 'wp_rest'),
        ]);

        // Route to refresh packages
        register_rest_route(Config::REST_NAMESPACE, '/packages/refresh', [
            'methods'  => 'POST',
            'callback' => [$this, 'refresh_packages'],
            'permission_callback' => Permissions::callback(Config::CAP_MANAGE, 'wp_rest'),
        ]);

        // Bulk actions: update many packages or set channel
        register_rest_route(Config::REST_NAMESPACE, '/packages/bulk', [
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'bulk_packages'],
            'permission_callback' => Permissions::callback(Config::CAP_MANAGE, 'wp_rest'),
            'args' => [
                'action' => ['required' => true, 'type' => 'string'],
                'repo_slugs' => ['required' => true, 'type' => 'array'],
                'channel' => ['required' => false, 'type' => 'string'],
            ],
        ]);
    }

    /**
     * Retrieves paginated packages.
     */
    public function get_packages(WP_REST_Request $request): WP_REST_Response {
        $page = max(1, (int) sanitize_text_field($request->get_param('page')));
        $per_page = max(1, min(100, (int) sanitize_text_field($request->get_param('per_page'))));

        Logger::info('Fetching packages.', [
            'page' => $page,
            'per_page' => $per_page
        ]);

        try {
            $packages = $this->packageService->get_paginated_packages($page, $per_page);
            $channels = get_option(Config::OPTION_RELEASE_CHANNELS, []);

            $normalizedPackages = [];
            if (is_array($packages)) {
                foreach ($packages as $pkg) {
                    $normalized = $this->normalize_package($pkg, $channels);
                    if (!empty($normalized)) {
                        $normalizedPackages[] = $normalized;
                    }
                }
            }

            return $this->respond([
                'packages' => $normalizedPackages,
                'unlinked_packages' => [],
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to retrieve packages.', [
                'exception' => $e->getMessage(),
                'page' => $page,
                'per_page' => $per_page
            ]);
            throw new CustomException(__('Failed to retrieve packages. Please check the logs for more details.', Config::TEXT_DOMAIN), 500);
        }
    }

    /**
     * Normalize package payload for REST responses.
     */
    private function normalize_package($package, array $channels): array
    {
        if (is_object($package) && method_exists($package, 'toArray')) {
            $package = $package->toArray();
        }

        if (!is_array($package)) {
            return [];
        }

        $repo = $package['repo'] ?? $package['repo_slug'] ?? $package['id'] ?? '';
        if (!$repo) {
            return [];
        }

        $channel = $channels[$repo] ?? ($package['channel'] ?? 'stable');
        $channel = strtolower((string) $channel ?: 'stable');

        $status = $package['status'] ?? ($package['metadata']['status'] ?? 'unknown');
        $latest = $package['latest'] ?? ($package['metadata']['latest'] ?? null);
        $name = $package['name'] ?? ($package['metadata']['name'] ?? $repo);
        $version = $package['version'] ?? ($package['metadata']['version'] ?? null);
        $type = $package['type'] ?? ($package['metadata']['type'] ?? null);
        $slug = $package['slug'] ?? basename((string) $repo);
        $updated = $package['last_updated'] ?? ($package['metadata']['last_updated'] ?? null);

        return [
            'id' => $package['id'] ?? $repo,
            'repo' => $repo,
            'slug' => $slug,
            'name' => $name,
            'version' => $version,
            'latest' => $latest,
            'status' => $status,
            'channel' => $channel,
            'type' => $type,
            'last_updated' => $updated,
        ];
    }

    /**
     * Triggers a package synchronization.
     */
    public function sync_packages(WP_REST_Request $request): WP_REST_Response {
        Logger::info('Package sync initiated via REST API.', [
            'params' => $request->get_params()
        ]);
        Logger::start('package_sync');
        try {
            $result = $this->packageService->get_all_packages_grouped();
            Logger::stop('package_sync');
            return $this->respond($result);
        } catch (\Exception $e) {
            Logger::error('Package sync failed.', [
                'exception' => $e->getMessage(),
                'params' => $request->get_params()
            ]);
            Logger::stop('package_sync');
            $errorMessage = __('Package synchronization failed. Please check the logs for more details.', Config::TEXT_DOMAIN);
            return $this->respond($errorMessage, 500);
        }
    }

    /**
     * Assigns a package (repository) to be managed by a specific app.
     */
    public function assign_package(WP_REST_Request $request): WP_REST_Response {
        $app_id = sanitize_text_field($request->get_param('app_id'));
        $repo_slug = sanitize_text_field($request->get_param('repo_slug'));

        if (empty($app_id) || empty($repo_slug)) {
            return $this->respond(__("App ID and repository slug are required.", Config::TEXT_DOMAIN), 400);
        }

        try {
            $this->packageService->assign_package_to_app($app_id, $repo_slug);
            return $this->respond(["message" => __("Package assigned successfully.", Config::TEXT_DOMAIN)]);
        } catch (\Exception $e) {
            return $this->respond($e->getMessage(), 500);
        }
    }

    /**
     * Enhanced error response with detailed messages.
     */
    private function respondWithError(string $userMessage, string $logMessage, int $statusCode = 500): WP_REST_Response {
        Logger::error($logMessage);
        return $this->respond([
            'error' => $userMessage,
            'code' => $statusCode
        ], $statusCode);
    }

    /**
     * Performs bulk operations on multiple packages.
     * Supported actions: update, set-channel
     */
    public function bulk_packages(WP_REST_Request $request): WP_REST_Response {
        $action = strtolower(sanitize_text_field($request->get_param('action') ?? ''));
        $repo_slugs = (array) ($request->get_param('repo_slugs') ?? []);
        $repo_slugs = array_filter(array_map('sanitize_text_field', $repo_slugs));
        $channel = $request->get_param('channel');
        if (is_string($channel)) { $channel = sanitize_text_field($channel); }

        if (empty($action) || empty($repo_slugs)) {
            return $this->respond(__('Invalid bulk request.', Config::TEXT_DOMAIN), 400);
        }

        $results = [];
        switch ($action) {
            case 'update':
                foreach ($repo_slugs as $slug) {
                    try {
                        $ok = $this->packageService->update_package($slug);
                        $results[] = ['repo' => $slug, 'ok' => (bool) $ok];
                    } catch (\Throwable $e) {
                        $results[] = ['repo' => $slug, 'ok' => false, 'error' => $e->getMessage()];
                    }
                }
                break;
            case 'set-channel':
                if (empty($channel)) {
                    return $this->respond(__('Channel is required for set-channel.', Config::TEXT_DOMAIN), 400);
                }
                foreach ($repo_slugs as $slug) {
                    try {
                        $this->packageService->update_release_channel($slug, (string) $channel);
                        $results[] = ['repo' => $slug, 'ok' => true];
                    } catch (\Throwable $e) {
                        $results[] = ['repo' => $slug, 'ok' => false, 'error' => $e->getMessage()];
                    }
                }
                break;
            default:
                return $this->respond(__('Unsupported bulk action.', Config::TEXT_DOMAIN), 400);
        }

        return $this->respond(['results' => $results]);
    }

    /**
     * Updates a single package with enhanced error handling.
     */
    public function update_package(WP_REST_Request $request): WP_REST_Response {
        $repo_slug = sanitize_text_field($request->get_param('repo_slug'));
        try {
            $success = $this->packageService->update_package($repo_slug);
            $message = $success ? 'Package updated successfully.' : 'Failed to update package.';
            return $this->respond(['message' => __($message, Config::TEXT_DOMAIN)], $success ? 200 : 500);
        } catch (\Throwable $e) {
            return $this->respondWithError(
                __('An unexpected error occurred. Please try again later.', Config::TEXT_DOMAIN),
                $e->getMessage()
            );
        }
    }

    /**
     * Rolls back a single package.
     */
    public function rollback_package(WP_REST_Request $request): WP_REST_Response {
        $repo_slug = sanitize_text_field($request->get_param('repo_slug'));
        $version = sanitize_text_field($request->get_param('version'));
        if (empty($version)) {
            return $this->respond(__("Version is required for rollback.", Config::TEXT_DOMAIN), 400);
        }
        try {
            $success = $this->packageService->rollback_package($repo_slug, $version);
            $message = $success ? 'Package rolled back successfully.' : 'Failed to roll back package.';
            return $this->respond(['message' => __($message, Config::TEXT_DOMAIN)], $success ? 200 : 500);
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
            return $this->respond(__("Repository slug is required.", Config::TEXT_DOMAIN), 400);
        }

        try {
            $release_notes = $this->packageService->get_version_release_notes($repo_slug, 'latest'); // Assuming 'latest' is the default version.
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
            return $this->respond(__("Repository slug and channel are required.", Config::TEXT_DOMAIN), 400);
        }

        try {
            $this->packageService->update_release_channel($repo_slug, $channel);
            return $this->respond(["message" => __("Release channel updated successfully.", Config::TEXT_DOMAIN)]);
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
            return $this->respond(__('Invalid parameters. The app_id is required.', Config::TEXT_DOMAIN), 400);
        }

        // Pre-flight checks
        try {
            $repo_exists = $this->packageService->check_repository_availability($name, $app_id);
            if (!$repo_exists) {
                return $this->respond(__('Repository does not exist or is inaccessible.', Config::TEXT_DOMAIN), 400);
            }
        } catch (\Exception $e) {
            return $this->respond(__('Pre-flight check failed: ', Config::TEXT_DOMAIN) . $e->getMessage(), 500);
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
                'message' => __('Packages refreshed successfully.', Config::TEXT_DOMAIN),
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
