<?php

declare(strict_types=1);

namespace Tests\Unit;

use WP2\Update\Utils\SharedUtils;
use WP2\Update\Core\GitHubApp\Init;
use Mockery as m;
use Tests\Unit\Mocks\StubGitHubAppInit;

test('correctly normalizes full GitHub URLs', function () {
    $mockGitHubApp = new StubGitHubAppInit();
    $utils = new SharedUtils($mockGitHubApp);
    $url = 'https://github.com/owner/repo-name/';
    expect($utils->normalize_repo($url))->toBe('owner/repo-name');
});

test('correctly normalizes simple slugs', function () {
    $mockGitHubApp = new StubGitHubAppInit();
    $utils = new SharedUtils($mockGitHubApp);
    $slug = 'owner/repo-name';
    expect($utils->normalize_repo($slug))->toBe('owner/repo-name');
});

test('returns null for invalid URIs', function () {
    $mockGitHubApp = new StubGitHubAppInit();
    $utils = new SharedUtils($mockGitHubApp);
    $invalid_uri = 'https://not-github.com/owner/repo';
    expect($utils->normalize_repo($invalid_uri))->toBeNull();
});