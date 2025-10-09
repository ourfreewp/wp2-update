<?php

namespace WP2\Update\Core\Updates;

use WP2\Update\Core\API\ReleaseService;
use WP2\Update\Core\API\GitHubClientFactory;
use WP2\Update\Utils\Formatting;

abstract class AbstractUpdater
{
    protected PackageFinder $packages;
    protected ReleaseService $releaseService;
    protected GitHubClientFactory $clientFactory;

    public function __construct(PackageFinder $packages, ReleaseService $releaseService, GitHubClientFactory $clientFactory)
    {
        $this->packages       = $packages;
        $this->releaseService = $releaseService;
        $this->clientFactory  = $clientFactory;
    }

    abstract protected function get_managed_items(): array;

    abstract protected function get_transient_hook(): string;

    public function register_hooks(): void
    {
        add_filter($this->get_transient_hook(), [$this, 'inject_updates']);
        add_filter('upgrader_pre_download', [$this, 'maybe_provide_authenticated_package'], 10, 4); // Corrected to 4 arguments
    }

    public function inject_updates(object $transient): object
    {
        if (empty($transient->checked) || !is_array($transient->checked)) {
            return $transient;
        }

        foreach ($this->get_managed_items() as $slug => $item) {
            $repoParts = explode('/', $item['repo']);
            if (count($repoParts) !== 2) {
                continue;
            }

            [$owner, $repo] = $repoParts;

            $latestRelease = $this->releaseService->get_latest_release($owner, $repo);
            if ($latestRelease && version_compare(Formatting::normalize_version($latestRelease['tag_name']), Formatting::normalize_version($transient->checked[$slug]), '>')) {
                $transient->response[$slug] = [
                    'new_version' => $latestRelease['tag_name'],
                    'package'     => $latestRelease['zipball_url'],
                    'slug'        => $slug,
                ];
            }
        }

        return $transient;
    }

    public function maybe_provide_authenticated_package($reply, string $package, $upgrader, array $hookExtra)
    {
        if (strpos($package, 'github.com') !== false) {
            $token = $this->clientFactory->getInstallationToken();
            if ($token) {
                $package = add_query_arg('access_token', $token, $package);
            }
        }

        return $package;
    }
}