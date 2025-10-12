<?php

namespace WP2\Update\REST\Controllers;

use WP2\Update\Security\Permissions;
use WP_REST_Request;
use WP_REST_Response;
use WP2\Update\Core\Updates\PackageService;
use WP2\Update\Utils\Logger;

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
        try {
            $data = $this->packageService->sync_packages();

            return new WP_REST_Response([
                'packages' => $data['packages'],
                'unlinked_packages' => $data['unlinked_packages'],
            ], 200);
        } catch (\Throwable $exception) {
            Logger::log('ERROR', 'Failed to sync packages: ' . $exception->getMessage());

            return new WP_REST_Response([
                'error' => true,
                'message' => esc_html__('Unable to sync packages.', 'wp2-update'),
            ], 500);
        }
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

                // Validate the action parameter against an allowlist of expected values
        $allowedActions = ['install', 'update', 'rollback'];
        if (!in_array($action, $allowedActions, true)) {
            return new WP_REST_Response(['message' => esc_html__('Invalid action parameter.', 'wp2-update')], 400);
        }

        try {
            if ($action === 'rollback') {
                if ('' === $version) {
                    return new WP_REST_Response(['message' => esc_html__('Version parameter is required for rollback.', 'wp2-update')], 400);
                }

                $success = $this->packageService->rollback_package($repoSlug, $version);

                if (!$success) {
                    return new WP_REST_Response(['message' => esc_html__('Failed to rollback package.', 'wp2-update')], 400);
                }

                return new WP_REST_Response(['message' => esc_html__('Package rolled back successfully.', 'wp2-update')], 200);
            }

            $success = $this->packageService->manage_packages($action, $repoSlug, $version, $type);

            if (!$success) {
                return new WP_REST_Response(['message' => esc_html__('Failed to manage package.', 'wp2-update')], 400);
            }

            return new WP_REST_Response(['message' => esc_html__('Package managed successfully.', 'wp2-update')], 200);
        } catch (\Exception $e) {
            // Log the detailed exception for debugging purposes
            Logger::log('ERROR', 'Exception in manage_packages: ' . $e->getMessage());

            // Return a generic error message to the client
            return new WP_REST_Response([
                'message' => esc_html__('An error occurred while managing the package.', 'wp2-update')
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
            return $this->format_response([
                'message' => esc_html__('No packages found.', 'wp2-update')
            ], 404);
        }

        return $this->format_response(['packages' => $packages]);
    }

    public function assign_package( WP_REST_Request $request ): WP_REST_Response {
        $appId = $request->get_param('app_id');
        $repoId = $request->get_param('repo_id');

        try {
            $this->packageService->assign_package_to_app($appId, $repoId);

            return $this->format_response([
                'success' => true, // Ensure success boolean is included
                'message' => esc_html__('Package assigned successfully.', 'wp2-update'),
            ]);
        } catch ( \Exception $e ) {
            Logger::log('ERROR', 'Failed to assign package: ' . $e->getMessage());
            return $this->format_response([
                'success' => false, // Ensure success boolean is included
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function toggle_auto_update(WP_REST_Request $request): WP_REST_Response {
        $packageId = $request->get_param('package_id');
        $autoUpdate = $request->get_param('auto_update');

        if (empty($packageId)) {
            return new WP_REST_Response([
                'message' => esc_html__('Package ID is required.', 'wp2-update')
            ], 400);
        }

        try {
            $success = $this->packageService->set_auto_update($packageId, $autoUpdate);

            if (!$success) {
                return new WP_REST_Response([
                    'message' => esc_html__('Failed to update auto-update setting.', 'wp2-update')
                ], 500);
            }

            return new WP_REST_Response([
                'message' => esc_html__('Auto-update setting updated successfully.', 'wp2-update')
            ], 200);
        } catch (\Throwable $exception) {
            Logger::log('ERROR', 'Failed to toggle auto-update: ' . $exception->getMessage());

            return new WP_REST_Response([
                'message' => esc_html__('An error occurred while updating auto-update setting.', 'wp2-update')
            ], 500);
        }
    }

    public function get_release_notes(WP_REST_Request $request): WP_REST_Response {
        $packageId = $request->get_param('package_id');

        if (empty($packageId)) {
            return new WP_REST_Response([
                'message' => esc_html__('Package ID is required.', 'wp2-update')
            ], 400);
        }

        try {
            $releaseNotes = $this->packageService->get_release_notes($packageId);

            return new WP_REST_Response([
                'notes' => $releaseNotes,
            ], 200);
        } catch (\Throwable $exception) {
            Logger::log('ERROR', 'Failed to fetch release notes: ' . $exception->getMessage());

            return new WP_REST_Response([
                'message' => esc_html__('An error occurred while fetching release notes.', 'wp2-update')
            ], 500);
        }
    }

    private function format_response(array $data, int $status = 200): WP_REST_Response {
        return new WP_REST_Response([
            'success' => $status >= 200 && $status < 300,
            'data'    => $data,
        ], $status);
    }
}
