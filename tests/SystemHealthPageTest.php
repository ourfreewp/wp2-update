<?php

namespace Tests;

use WP2\Update\Admin\Pages\SystemHealthPage;
use WP2\Update\Core\Connection\Init as Connection;
use WP2\Update\Core\API\GitHubApp\Init as GitHubApp;
use WP2\Update\Core\API\Service as GitHubService;
use WP2\Update\Utils\SharedUtils;
use WP2\Update\Core\Updates\PackageFinder;
use Brain\Monkey\Functions;

class SystemHealthPageTest extends TestCase
{
    public function testGitHubApiStatusIsConnected()
    {
        Functions\when('wp_get_themes')->justReturn([
            'theme-slug' => new class {
                public function get($key) {
                    return $key === 'UpdateURI' ? 'https://github.com/owner/repo' : null;
                }
            },
        ]);

        Functions\when('set_transient')->justReturn(true);
        Functions\when('get_transient')->justReturn(null);
        Functions\when('defined')->alias(function ($constant) {
            return $constant === 'HOUR_IN_SECONDS';
        });
        Functions\when('constant')->alias(function ($constant) {
            return $constant === 'HOUR_IN_SECONDS' ? 3600 : null;
        });

        Functions\when('WP_Query')->alias(function ($args) {
            return new class {
                public function have_posts() {
                    return false;
                }
            };
        });

        $realGitHubService = new GitHubService();
        $realGitHubApp = new GitHubApp($realGitHubService);

        $realUtils = new SharedUtils($realGitHubApp, $realGitHubService);
        $realPackageFinder = new PackageFinder($realUtils);
        $realConnection = new Connection($realPackageFinder);

        $systemHealthPage = new SystemHealthPage(
            $realConnection,
            $realGitHubApp,
            $realUtils
        );

        $status = $systemHealthPage->get_github_api_status();

        $this->assertEquals('Connected', $status['GitHub App Connection']);
        $this->assertEquals('Connected to GitHub App.', $status['Message']);
    }

    public function testGitHubApiStatusIsNotConnected()
    {
        Functions\when('wp_get_themes')->justReturn([
            'theme-slug' => new class {
                public function get($key) {
                    return null;
                }
            },
        ]);

        Functions\when('set_transient')->justReturn(true);
        Functions\when('get_transient')->justReturn(null);
        Functions\when('defined')->alias(function ($constant) {
            return $constant === 'HOUR_IN_SECONDS';
        });
        Functions\when('constant')->alias(function ($constant) {
            return $constant === 'HOUR_IN_SECONDS' ? 3600 : null;
        });

        Functions\when('WP_Query')->alias(function ($args) {
            return new class {
                public function have_posts() {
                    return false;
                }
            };
        });

        $realGitHubService = new GitHubService();
        $realGitHubApp = new GitHubApp($realGitHubService);

        $realUtils = new SharedUtils($realGitHubApp, $realGitHubService);
        $realPackageFinder = new PackageFinder($realUtils);
        $realConnection = new Connection($realPackageFinder);

        $systemHealthPage = new SystemHealthPage(
            $realConnection,
            $realGitHubApp,
            $realUtils
        );

        $status = $systemHealthPage->get_github_api_status();

        $this->assertEquals('Not Connected', $status['GitHub App Connection']);
        $this->assertEquals('Not connected to GitHub App.', $status['Message']);
    }
}
