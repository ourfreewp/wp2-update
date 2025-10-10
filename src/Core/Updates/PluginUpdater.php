<?php

namespace WP2\Update\Core\Updates;

use WP2\Update\Core\API\ReleaseService;
use WP2\Update\Core\API\GitHubClientFactory;
use WP2\Update\Core\API\RepositoryService;

/**
 * Hooks WordPress' plugin update API into GitHub releases.
 */
class PluginUpdater extends AbstractUpdater
{
    protected PackageFinder $packages;
    protected ReleaseService $releaseService;

    public function __construct(PackageFinder $packages, ReleaseService $releaseService, GitHubClientFactory $clientFactory, RepositoryService $repositoryService)
    {
        parent::__construct($packages, $releaseService, $clientFactory, $repositoryService, 'plugin');
    }
}
