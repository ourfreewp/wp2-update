<?php

namespace Tests\Unit\Mocks;

use WP2\Update\Core\API\Service;

class StubGitHubService extends Service {
    public function call(string $app_slug, string $method, string $path, array $params = []): array {
        // Provide a default implementation for testing
        return ['status' => 'success', 'data' => []];
    }
}