<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WP2\Update\Core\API\Service;
use WP2\Update\Core\API\GitHubApp\Init as GitHubAppInit;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use WP2\Update\Utils\Logger;
use Brain\Monkey\Functions;
use Tests\Feature\Mocks\MockService;
use Tests\TestCase as BaseTestCase;

class GitHubAppAuthenticationTest extends TestCase
{
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $mockRequestFactory = $this->createMock(RequestFactoryInterface::class);
        $mockStreamFactory = $this->createMock(StreamFactoryInterface::class);

        $mockWpQueryClass = get_class(new class {
            public function have_posts() {
                return true;
            }

            public function the_post() {}

            public function post() {
                return (object) [
                    'ID' => 1,
                    'post_title' => 'Test App',
                    'post_content' => 'Test Content',
                ];
            }

            public $posts;

            public function __construct() {
                $this->posts = [(object) ['ID' => 1]];
            }
        });

        $this->service = new MockService(); // Ensure MockService is explicitly used
        Logger::log_debug('Debug: MockService explicitly set in setup', 'test');

        Functions\when('is_admin')->justReturn(false);

        Functions\when('__')->alias(function ($text, $domain) {
            return $text;
        });

        Functions\when('get_site_option')->alias(function ($option, $default) {
            static $options = [];
            return $options[$option] ?? $default;
        });

        Functions\when('WP2\\Update\\Utils\\current_time')->alias(function ($type) {
            return time();
        });

        Functions\when('update_site_option')->alias(function ($option, $value) {
            static $logs = [];
            $logs[$option] = $value;
            return true;
        });

        Logger::setOptionHandlers(
            fn($key, $default) => $default,
            fn($key, $value) => null,
            fn($type) => time()
        );

        error_log('Debug: Logger::setOptionHandlers called in setUp');

        Logger::log_debug('Debug: Service class used: ' . get_class($this->service), 'test');
    }

    public function testGenerateJwt(): void
    {
        $appSlug = 'test-app';

        $jwt = $this->service->test_create_app_jwt($appSlug);

        $this->assertNotNull($jwt, 'JWT should not be null');
        $this->assertIsString($jwt, 'JWT should be a string');
    }

    public function testGetInstallationToken(): void
    {
        Logger::log_debug('Debug: Starting testGetInstallationToken', 'test');

        $appSlug = 'test-app';

        $token = $this->service->test_get_installation_token($appSlug);

        $this->assertNotNull($token, 'Installation Access Token should not be null');
        $this->assertIsString($token, 'Installation Access Token should be a string');
        $this->assertSame('mock-installation-token', $token, 'Expected mock installation token');

        Logger::log_debug('Debug: Token value is ' . var_export($token, true), 'test');
    }

    public function testMockServiceDirectly(): void
    {
        $mockService = new MockService();
        $token = $mockService->get_installation_token('test-app');

        $this->assertSame('mock-installation-token', $token, 'Expected mock installation token');
    }
}
