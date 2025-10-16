<?php

namespace WP2\Update\Updates;

use WP2\Update\Services\PackageService;
use WP2\Update\Services\Github\ReleaseService;
use WP2\Update\Services\Github\ClientService;
use WP2\Update\Services\Github\RepositoryService;
use WP2\Update\Utils\Logger;

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
     * Checks for updates for all managed packages of this type with caching.
     */
    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        Logger::info('Checking for updates.', ['type' => $this->type]);
        Logger::start('update_check_' . $this->type);

        $cache_key = 'wp2_update_' . $this->type . '_updates';
        $cached_updates = get_transient($cache_key);

        if ($cached_updates !== false) {
            $transient->response = array_merge($transient->response ?? [], $cached_updates);
            Logger::info('Updates found in cache.', ['type' => $this->type, 'updates' => $cached_updates]);
            return $transient;
        }

        $packages = ($this->type === 'plugin')
            ? $this->packageService->getManagedPlugins()
            : $this->packageService->getManagedThemes();

        $updates = [];
        foreach ($packages as $slug => $package) {
            if (isset($transient->checked[$slug])) {
                $update = $this->get_update_data($package, $transient->checked[$slug]);
                if ($update) {
                    $updates[$slug] = (object) $update;
                }
            }
        }

        if (!empty($updates)) {
            set_transient($cache_key, $updates, 5 * MINUTE_IN_SECONDS);
            $transient->response = array_merge($transient->response ?? [], $updates);
            Logger::info('Updates found.', ['type' => $this->type, 'updates' => $updates]);
        } else {
            Logger::info('No updates found.', ['type' => $this->type]);
        }

        Logger::stop('update_check_' . $this->type);
        return $transient;
    }

    /**
     * Gets update data for a single package if an update is available, considering the release channel.
     */
    private function get_update_data(array $package, string $installed_version): ?array {
        $release_channel = $package['release_channel'] ?? 'stable'; // Default to stable if not set
        $latest_release = $this->releaseService->get_latest_release($package['repo'], $release_channel);

        if (!$latest_release || version_compare($installed_version, $latest_release['tag_name'], '>=')) {
            return null;
        }

        // Allow modification of the download URL via a filter hook
        $download_url = apply_filters('wp2/update/download_url', $this->releaseService->get_zip_url_from_release($latest_release), $package);

        return [
            'slug'        => $package['slug'],
            'new_version' => $latest_release['tag_name'],
            'url'         => $latest_release['html_url'],
            'package'     => $download_url,
        ];
    }
}
