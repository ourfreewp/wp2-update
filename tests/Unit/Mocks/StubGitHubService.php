<?php

namespace Tests\Unit\Mocks;

use Brain\Monkey;

class StubGitHubService {
    public static function mock() {
        Monkey\Functions\when('WP2\\Update\\Core\\API\\Service::call')->justReturn([
            'status' => 'success',
            'data' => []
        ]);
    }
}