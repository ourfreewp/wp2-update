<?php

namespace WP2\Update\Updates;

use WP2\Update\Services\PackageService;
use WP2\Update\Services\Github\ReleaseService;
use WP2\Update\Services\Github\ClientService;
use WP2\Update\Services\Github\RepositoryService;

/**
 * Abstract base class for handling plugin and theme updates from GitHub.
 */
abstract class AbstractUpdater {
    protected PackageService $packageService;
    protected ReleaseService $releaseService;
    protected ClientService $clientService;
    protected RepositoryService $repositoryService;
    protected string $type; // 'plugin' or 'theme'

    public function __construct(
        PackageService $packageService,
        ReleaseService $releaseService,
        ClientService $clientService,
        RepositoryService $repositoryService,
        string $type
    ) {
        $this->packageService = $packageService;
        $this->releaseService = $releaseService;
        $this->clientService = $clientService;
        $this->repositoryService = $repositoryService;
        $this->type = $type;
    }

    /**
     * Registers the necessary WordPress filter hooks.
     */
    public function register_hooks(): void {
        $transient_hook = 'pre_set_site_transient_update_' . $this->type . 's';
        add_filter($transient_hook, [$this, 'check_for_updates']);
    }

    /**
     * Checks for updates for all managed packages of this type.
     */
    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $packages = ($this->type === 'plugin')
            ? $this->packageService->get_managed_plugins()
            : $this->packageService->get_managed_themes();

        foreach ($packages as $slug => $package) {
            if (isset($transient->checked[$slug])) {
                $update = $this->get_update_data($package, $transient->checked[$slug]);
                if ($update) {
                    $transient->response[$slug] = (object) $update;
                }
            }
        }

        return $transient;
    }

    /**
     * Gets update data for a single package if an update is available.
     */
    private function get_update_data(array $package, string $installed_version): ?array {
        $latest_release = $this->releaseService->get_latest_release($package['repo']);

        if (!$latest_release || version_compare($installed_version, $latest_release['tag_name'], '>=')) {
            return null;
        }

        return [
            'slug'        => $package['slug'],
            'new_version' => $latest_release['tag_name'],
            'url'         => $latest_release['html_url'],
            'package'     => $this->releaseService->get_zip_url_from_release($latest_release),
        ];
    }
}
