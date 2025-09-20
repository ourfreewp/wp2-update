<?php

namespace Tests\Feature;

require_once __DIR__ . '/../../vendor/autoload.php';

use Brain\Monkey\Functions;
use WP2\Update\Core\Updates\PackageFinder;
use Tests\Feature\Mocks\MockWPTheme;
use ReflectionClass;

// Mock class for testing purposes
// class MockWPTheme {
//     public function get($header) {
//         if ($header === 'Update URI') return 'owner/my-theme';
//         if ($header === 'Name') return 'My Awesome Theme';
//         return '';
//     }
// }

beforeEach(function () {
    // Mock WordPress functions dynamically
    Functions::when('get_plugins')->justReturn([
        'my-plugin-slug/my-plugin.php' => [
            'Name' => 'My Awesome Plugin',
            'UpdateURI' => 'owner/my-plugin',
        ],
    ]);
});

it('discovers managed themes with a valid update uri', function () {
    $finder = new PackageFinder();

    // Manually mock the `find_app_for_repo` method
    $reflection = new ReflectionClass($finder);
    $method = $reflection->getMethod('find_app_for_repo');
    $method->setAccessible(true);

    $method->invokeArgs($finder, ['owner/my-theme']);

    $managed_themes = $finder->get_managed_themes();

    expect($managed_themes)->toHaveKey('my-theme-slug');
    expect($managed_themes['my-theme-slug']['name'])->toBe('My Awesome Theme');
    expect($managed_themes['my-theme-slug']['repo'])->toBe('owner/my-theme');
});

it('discovers managed plugins with a valid update uri', function () {
    $finder = new PackageFinder();

    $managed_plugins = $finder->get_managed_plugins();

    expect($managed_plugins)->toHaveKey('my-plugin-slug');
    expect($managed_plugins['my-plugin-slug']['name'])->toBe('My Awesome Plugin');
    expect($managed_plugins['my-plugin-slug']['repo'])->toBe('owner/my-plugin');
});

it('verifies Brain Monkey integration', function () {
    Functions::when('get_transient')->justReturn('mocked_value');

    $result = get_transient('any_key');

    expect($result)->toBe('mocked_value');
});