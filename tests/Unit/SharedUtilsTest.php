<?php

declare(strict_types=1);

namespace Tests\Unit;

use WP2\Update\Utils\SharedUtils;
use Brain\Monkey\Expectation\ExpectationFactory;
use Mockery;
use Tests\TestCase;
use WP2\Update\Core\API\GitHubApp\Init as GitHubAppInit;
use WP2\Update\Core\API\Service as GitHubService;

class SharedUtilsTest extends TestCase
{
    private $realGitHubService;
    private $realGitHubApp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->realGitHubService = new GitHubService();
        $this->realGitHubApp = new GitHubAppInit($this->realGitHubService);
    }

    public function testNormalizeRepoUrl()
    {
        $utils = new SharedUtils($this->realGitHubApp, $this->realGitHubService);
        $url = 'https://github.com/owner/repo-name/';
        $this->assertEquals('owner/repo-name', $utils->normalize_repo($url));
    }

    public function testCorrectlyNormalizesSimpleSlugs()
    {
        $utils = new SharedUtils($this->realGitHubApp, $this->realGitHubService);
        $slug = 'owner/repo-name';
        $this->assertEquals('owner/repo-name', $utils->normalize_repo($slug));
    }

    public function testReturnsNullForInvalidUris()
    {
        $utils = new SharedUtils($this->realGitHubApp, $this->realGitHubService);
        $invalid_uri = 'https://not-github.com/owner/repo';
        $this->assertNull($utils->normalize_repo($invalid_uri));
    }

    public function testCorrectlyNormalizesVersionStrings()
    {
        $utils = new SharedUtils($this->realGitHubApp, $this->realGitHubService);

        $version = 'v1.2.3';
        $this->assertEquals('1.2.3', $utils->normalize_version($version));

        $version = '1.2.3';
        $this->assertEquals('1.2.3', $utils->normalize_version($version));

        $version = null;
        $this->assertEquals('0.0.0', $utils->normalize_version($version));
    }
}