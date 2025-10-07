<?php

declare(strict_types=1);

namespace Tests\Feature\Mocks;

use WP2\Update\Core\API\Service;
use WP2\Update\Utils\Logger;

class MockService extends Service {
    public function __construct() {
        // No constructor logic for easier mocking.
    }

    public function get_app_credentials(string $app_slug): ?array {
        return [
            'app_id' => 'test-app-id',
            'installation_id' => 12345,
            'private_key' => 'test-private-key',
        ];
    }

    public function create_app_jwt(string $app_slug): ?string {
        return 'mock-jwt';
    }

    public function get_installation_token(string $app_slug): ?string {
        Logger::log_debug("Debug: get_installation_token called with app_slug: {$app_slug}", 'test');
        Logger::log_debug('Debug: Returning mock installation token', 'test');
        Logger::log_debug('Debug: MockService get_installation_token executed', 'test');
        return 'mock-installation-token';
    }
}