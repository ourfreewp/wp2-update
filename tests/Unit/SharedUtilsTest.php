<?php

declare(strict_types=1);

namespace Tests\Unit;

use WP2\Update\Utils\SharedUtils;
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

test('correctly normalizes version strings', function () {
    $mockGitHubApp = new StubGitHubAppInit();
    $utils = new SharedUtils($mockGitHubApp);

    $version = 'v1.2.3';
    expect($utils->normalize_version($version))->toBe('1.2.3');

    $version = '1.2.3';
    expect($utils->normalize_version($version))->toBe('1.2.3');

    $version = null;
    expect($utils->normalize_version($version))->toBe('0.0.0');
});