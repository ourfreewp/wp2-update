<?php

namespace Tests\Unit\Mocks;

use Brain\Monkey;

class StubGitHubAppInit {
    public static function mock() {
        StubGitHubService::mock();
        Monkey\Functions\when('WP2\\Update\\Core\\API\\GitHubApp\\Init::__construct')->justReturn(null);
    }
}