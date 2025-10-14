<?php

namespace WP2\Update\Services;

use WP2\Update\Services\Github\AppService;
use WP2\Update\Services\PackageService;

/**
 * Mediates interactions between AppService and PackageService.
 */
class AppPackageMediator {
    private AppService $appService;
    private PackageService $packageService;

    public function __construct(AppService $appService, PackageService $packageService) {
        $this->appService = $appService;
        $this->packageService = $packageService;
    }

    /**
     * Assigns a package to an app by updating the app's managed repositories.
     *
     * @param string $app_id The app ID.
     * @param string $repo_slug The repository slug.
     * @return void
     */
    public function assignPackageToApp(string $app_id, string $repo_slug): void {
        $app_data = $this->appService->getAppData($app_id);
        if (!$app_data) {
            throw new \RuntimeException("App not found: {$app_id}");
        }

        $managed_repositories = $app_data['managed_repositories'] ?? [];
        if (!in_array($repo_slug, $managed_repositories, true)) {
            $managed_repositories[] = $repo_slug;
            $app_data['managed_repositories'] = $managed_repositories;

            $this->appService->saveAppData($app_data);
        }
    }

    /**
     * Retrieves all packages grouped by their app status.
     *
     * @return array
     */
    public function getAllPackagesGrouped(): array {
        $local_packages = array_merge(
            $this->packageService->getManagedPlugins(),
            $this->packageService->getManagedThemes()
        );
        $managed_repos_by_app = $this->appService->getManagedRepositoriesByApp();

        $result = ['managed' => [], 'unlinked' => [], 'all' => []];

        foreach ($local_packages as $package) {
            $processed_package = $this->packageService->processPackage($package, $managed_repos_by_app);
            $result['all'][] = $processed_package;

            if ($processed_package['is_managed']) {
                $result['managed'][] = $processed_package;
            } else {
                $result['unlinked'][] = $processed_package;
            }
        }

        return $result;
    }
}