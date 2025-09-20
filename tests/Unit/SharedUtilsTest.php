<?php

declare(strict_types=1);

namespace Tests\Unit;

use WP2\Update\Utils\SharedUtils;
use WP2\Update\Core\GitHubApp\Init as GitHubAppInit;
use Mockery as m;

test('correctly normalizes full GitHub URLs', function () {
    $mockGitHubApp = m::mock(GitHubAppInit::class);
    $mockGitHubApp->shouldReceive('someMethod')->andReturn('someValue');

    $utils = new SharedUtils($mockGitHubApp);
    $url = 'https://github.com/owner/repo-name/';
    expect($utils->normalize_repo($url))->toBe('owner/repo-name');
});

test('correctly normalizes simple slugs', function () {
    $mockGitHubApp = m::mock(GitHubAppInit::class);
    $mockGitHubApp->shouldReceive('someMethod')->andReturn('someValue');

    $utils = new SharedUtils($mockGitHubApp);
    $slug = 'owner/repo-name';
    expect($utils->normalize_repo($slug))->toBe('owner/repo-name');
});

test('returns null for invalid URIs', function () {
    $mockGitHubApp = m::mock(GitHubAppInit::class);
    $mockGitHubApp->shouldReceive('someMethod')->andReturn('someValue');

    $utils = new SharedUtils($mockGitHubApp);
    $invalid_uri = 'https://not-github.com/owner/repo';
    expect($utils->normalize_repo($invalid_uri))->toBeNull();
});