<?php

namespace Tests\Unit\Mocks;

use WP2\Update\Core\API\GitHubApp\Init;
use WP2\Update\Core\API\Service as GitHubService;

class StubGitHubAppInit extends Init {
    public function __construct() {
        $stubGitHubService = new StubGitHubService();
        parent::__construct($stubGitHubService);
    }
}