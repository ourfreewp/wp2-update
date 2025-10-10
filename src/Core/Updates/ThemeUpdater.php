<?php

namespace WP2\Update\Core\Updates;

use WP2\Update\Core\API\ReleaseService;
use WP2\Update\Core\API\GitHubClientFactory;
use WP2\Update\Core\API\RepositoryService;

/**
 * Integrates theme updates with GitHub releases.
 */
class ThemeUpdater extends AbstractUpdater
{
    protected ReleaseService $releaseService;
    protected GitHubClientFactory $clientFactory;
    protected RepositoryService $repositoryService;

    public function __construct(PackageFinder $packages, ReleaseService $releaseService, GitHubClientFactory $clientFactory, RepositoryService $repositoryService)
    {
        parent::__construct($packages, $releaseService, $clientFactory, $repositoryService, 'theme');
    }
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
