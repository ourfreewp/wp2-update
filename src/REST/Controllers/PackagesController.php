<?php

namespace WP2\Update\Rest\Controllers;

use WP2\Update\Security\Permissions;
use WP2\Update\Core\Updates\PackageFinder;
use WP2\Update\Core\API\Service as GitHubService;
use WP2\Update\Utils\SharedUtils;
use WP_REST_Request;
use WP_REST_Response;
use InvalidArgumentException;
use Exception;
use WP2\Update\Core\API\GitHubClientFactory;
use WP2\Update\Core\API\CredentialService;
use WP2\Update\Core\Updates\PackageService;
use WP2\Update\Core\API\RepositoryService;
use WP2\Update\Core\API\ReleaseService;
use WP2\Update\Core\API\GitHubApp\Init as GitHubAppInit;

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
        $result = $this->packageService->sync_packages();
        return new WP_REST_Response($result, 200);
    }

    public function manage_packages(WP_REST_Request $request): WP_REST_Response {
        $action  = (string) $request->get_param('action');
        $package = (string) $request->get_param('package');
        $version = (string) $request->get_param('version');
        $type    = (string) $request->get_param('type');

        $success = $this->packageService->manage_packages($action, $package, $version, $type);

        if (!$success) {
            return new WP_REST_Response(['message' => 'Failed to manage package.'], 400);
        }

        return new WP_REST_Response(['message' => 'Package managed successfully.'], 200);
    }

    public function rest_get_package_status(WP_REST_Request $request): WP_REST_Response {
        $repoSlug = $request->get_param('repo_slug');

        if (empty($repoSlug)) {
            return new WP_REST_Response(['message' => 'Repository slug is required.'], 400);
        }

        $status = $this->packageService->get_package_status($repoSlug);

        if (!$status) {
            return new WP_REST_Response(['message' => 'Package not found.'], 404);
        }

        return new WP_REST_Response(['status' => $status], 200);
    }
}