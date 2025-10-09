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

    public function __construct(PackageFinder $packages, ReleaseService $releaseService, GitHubClientFactory $clientFactory, RepositoryService $repositoryService)
    {
        $this->packages          = $packages;
        $this->releaseService    = $releaseService;
        $this->clientFactory     = $clientFactory;
        $this->repositoryService = $repositoryService;
    }

    abstract protected function get_managed_items(): array;

    abstract protected function get_transient_hook(): string;

    public function register_hooks(): void
    {
        add_filter($this->get_transient_hook(), [$this, 'inject_updates']);
        add_filter('http_request_args', [$this, 'add_authorization_header'], 99, 2);
    }

    public function inject_updates(object $transient): object
    {
        if (empty($transient->checked) || !is_array($transient->checked)) {
            return $transient;
        }

        $managedPackages = $this->packages->get_managed_packages();

        foreach ($managedPackages as $slug => $package) {
            $installedVersion = $transient->checked[$slug] ?? null;
            $latestRelease = $package['releases'][0] ?? null; // Assuming releases are sorted by version descending

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

    public function inject_authorization_header(array $args, string $url): array
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