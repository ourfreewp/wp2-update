<?php

namespace WP2\Update\REST\Controllers;

use WP2\Update\Security\Permissions;
use WP_REST_Request;
use WP_REST_Response;
use WP2\Update\Core\Updates\PackageService;

final class PackagesController {
    private PackageService $packageService;

    public function __construct(PackageService $packageService) {
        $this->packageService = $packageService;
    }

    public static function check_permissions(WP_REST_Request $request): bool {
        return Permissions::current_user_can_manage($request);
    }

    public function rest_run_update_check(WP_REST_Request $request): WP_REST_Response {
        wp_update_plugins();
        wp_update_themes();
        return new WP_REST_Response([
            'success' => true,
            'message' => esc_html__('Update check completed successfully.', 'wp2-update'),
        ], 200);
    }

    public function sync_packages(WP_REST_Request $request): WP_REST_Response {
        $packages = $this->packageService->sync_packages();
        return new WP_REST_Response(['packages' => $packages], 200);
    }

    public function manage_packages(WP_REST_Request $request): WP_REST_Response {
        $action  = (string) $request->get_param('action');
        $repoSlug = (string) ($request->get_param('package') ?: $request->get_param('repo_slug'));
        $version = (string) $request->get_param('version');
        $typeParam = $request->get_param('type');
        $type    = is_string($typeParam) ? $typeParam : null;

        if ('' === $action || '' === $repoSlug || '' === $version) {
            return new WP_REST_Response(['message' => esc_html__('Missing required parameters.', 'wp2-update')], 400);
        }

        try {
            $success = $this->packageService->manage_packages($action, $repoSlug, $version, $type);

            if (!$success) {
                return new WP_REST_Response(['message' => esc_html__('Failed to manage package.', 'wp2-update')], 400);
            }

            return new WP_REST_Response(['message' => esc_html__('Package managed successfully.', 'wp2-update')], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function rest_get_package_status(WP_REST_Request $request): WP_REST_Response {
        $repoSlug = $request->get_param('repo_slug');

        if (empty($repoSlug)) {
            return new WP_REST_Response(['message' => esc_html__('Repository slug is required.', 'wp2-update')], 400);
        }

        $status = $this->packageService->get_package_status($repoSlug);

        if (!$status) {
            return new WP_REST_Response(['message' => esc_html__('Package not found.', 'wp2-update')], 404);
        }

        return new WP_REST_Response(['package' => $status], 200);
    }

    public function rest_get_packages(WP_REST_Request $request): WP_REST_Response {
        $packages = $this->packageService->get_all_packages();

        if (empty($packages)) {
            return new WP_REST_Response(['message' => esc_html__('No packages found.', 'wp2-update')], 404);
        }

        return new WP_REST_Response(['packages' => $packages], 200);
    }
}
