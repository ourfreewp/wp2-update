<?php
declare(strict_types=1);

namespace WP2\Update\Services;

use WP2\Update\Services\Github\AppService;
use WP2\Update\Services\PackageService;
use WP2\Update\Utils\Logger;

/**
 * Class AppPackageMediator
 *
 * Mediates interactions between AppService and PackageService.
 */
class AppPackageMediator {
    /**
     * @var AppService Handles operations related to GitHub Apps.
     */
    private AppService $appService;

    /**
     * @var PackageService Handles operations related to packages.
     */
    private PackageService $packageService;

    /**
     * Constructor for AppPackageMediator.
     *
     * @param AppService $appService Handles operations related to GitHub Apps.
     * @param PackageService $packageService Handles operations related to packages.
     */
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
     * @throws \RuntimeException If the app is not found.
     */
    public function assignPackageToApp(string $app_id, string $repo_slug): void {
        Logger::info('Assigning package to app.', ['app_id' => $app_id, 'repo_slug' => $repo_slug]);

        $app_data = $this->appService->get_app_data($app_id);
        if (!$app_data) {
            Logger::warning('App not found.', ['app_id' => $app_id]);
            throw new \RuntimeException("App not found: {$app_id}");
        }

        $managed_repositories = $app_data['managed_repositories'] ?? [];
        if (!in_array($repo_slug, $managed_repositories, true)) {
            $managed_repositories[] = $repo_slug;
            $app_data['managed_repositories'] = $managed_repositories;

            $this->appService->save_app_data($app_data);
            Logger::info('Package successfully assigned to app.', ['app_id' => $app_id, 'repo_slug' => $repo_slug]);
        }
    }

    /**
     * Retrieves all packages grouped by their app status.
     *
     * @return array
     */
    public function getAllPackagesGrouped(): array {
        Logger::info('Retrieving all packages grouped by app status.');

        $local_packages = array_merge(
            $this->packageService->getManagedPlugins(),
            $this->packageService->getManagedThemes()
        );
        $managed_repos_by_app = $this->appService->get_managed_repositories_by_app();

        $result = ['managed' => [], 'unlinked' => [], 'all' => []];

        foreach ($local_packages as $package) {
            $processed_package = $this->packageService->processPackage($package);
            $result['all'][] = $processed_package;

            if ($processed_package['is_managed']) {
                $result['managed'][] = $processed_package;
            } else {
                $result['unlinked'][] = $processed_package;
            }
        }

        Logger::info('Packages grouped successfully.', [
            'managed_count' => count($result['managed']),
            'unlinked_count' => count($result['unlinked']),
            'total_count' => count($result['all']),
        ]);

        return $result;
    }

    /**
     * Clears the package cache for a given repository slug.
     */
    public function clearPackageCache(string $repoSlug): void {
        Logger::info('Clearing package cache.', ['repo_slug' => $repoSlug]);
        $this->packageService->refresh_packages();
        Logger::info('Package cache refreshed successfully.', ['repo_slug' => $repoSlug]);
    }

    /**
     * Retrieves app details for a given app ID.
     */
    public function getAppDetails(string $appId): array {
        Logger::info('Retrieving app data.', ['app_id' => $appId]);
        $details = $this->appService->get_app_data($appId);
        if ($details === null) {
            Logger::warning('App data not found.', ['app_id' => $appId]);
            throw new \RuntimeException("App data not found for ID: {$appId}");
        }
        Logger::info('App data retrieved successfully.', ['app_id' => $appId]);
        return $details;
    }
}