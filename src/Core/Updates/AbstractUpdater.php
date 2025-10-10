<?php

namespace WP2\Update\Core\Updates;

use WP2\Update\Core\API\ReleaseService;
use WP2\Update\Core\API\GitHubClientFactory;
use WP2\Update\Core\API\RepositoryService;

abstract class AbstractUpdater
{
    protected PackageFinder $packages;
    protected ReleaseService $releaseService;
    protected GitHubClientFactory $clientFactory;
    protected RepositoryService $repositoryService;
    protected string $updateType;

    public function __construct(PackageFinder $packages, ReleaseService $releaseService, GitHubClientFactory $clientFactory, RepositoryService $repositoryService, string $updateType)
    {
        $this->packages          = $packages;
        $this->releaseService    = $releaseService;
        $this->clientFactory     = $clientFactory;
        $this->repositoryService = $repositoryService;
        $this->updateType        = $updateType;
    }

    /**
     * Get the transient hook for updates.
     *
     * @return string The transient hook name.
     */
    protected function get_transient_hook(): string
    {
        return $this->updateType === 'plugin'
            ? 'pre_set_site_transient_update_plugins'
            : 'pre_set_site_transient_update_themes';
    }

    /**
     * Get managed items for updates.
     *
     * @return array The managed items.
     */
    protected function get_managed_items(): array
    {
        return $this->updateType === 'plugin'
            ? $this->packages->get_managed_plugins()
            : $this->packages->get_managed_themes();
    }

    public function register_hooks(): void
    {
        add_filter($this->get_transient_hook(), [$this, 'inject_updates']);
        add_filter('http_request_args', [$this, 'add_authorization_header'], 99, 2);
    }

    /**
     * Add update data to the transient.
     *
     * @param object $transient The transient object.
     * @return object The modified transient object.
     */
    public function inject_updates(object $transient): object
    {
        return $this->inject_update_data($transient, $this->get_managed_items());
    }

    /**
     * Inject update data into the transient.
     *
     * @param object $transient The transient object.
     * @param array $managedItems The managed items to inject.
     * @return object The modified transient object.
     */
    protected function inject_update_data(object $transient, array $managedItems): object
    {
        if (empty($transient->checked) || !is_array($transient->checked)) {
            return $transient;
        }

        foreach ($managedItems as $slug => $item) {
            $installedVersion = $transient->checked[$slug] ?? null;
            $latestRelease = $item['releases'][0] ?? null; // Assuming releases are sorted by version descending

            if ($latestRelease && version_compare($latestRelease['version'], $installedVersion, '>')) {
                $transient->response[$slug] = [
                    'new_version' => $latestRelease['version'],
                    'package'     => $latestRelease['download_url'],
                    'slug'        => $slug,
                ];
            }
        }

        return $transient;
    }

    public function add_authorization_header(array $args, string $url): array
    {
        if (strpos($url, 'github.com') !== false) {
            $token = $this->clientFactory->getInstallationToken();
            if ($token) {
                $args['headers']['Authorization'] = 'Bearer ' . $token;
            }
        }
        return $args;
    }
}