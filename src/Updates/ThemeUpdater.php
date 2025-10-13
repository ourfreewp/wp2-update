<?php

namespace WP2\Update\Updates;

use WP2\Update\Services\PackageService;
use WP2\Update\Services\Github\ReleaseService;
use WP2\Update\Services\Github\ClientService;
use WP2\Update\Services\Github\RepositoryService;

/**
 * Hooks into WordPress's theme update API to provide updates from GitHub releases.
 */
class ThemeUpdater extends AbstractUpdater {
    public function __construct(
        PackageService $packageService,
        ReleaseService $releaseService,
        ClientService $clientService,
        RepositoryService $repositoryService
    ) {
        parent::__construct($packageService, $releaseService, $clientService, $repositoryService, 'theme');
    }
}
