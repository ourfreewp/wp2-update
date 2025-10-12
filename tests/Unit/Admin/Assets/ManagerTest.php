<?php

use Tests\Helpers\WordPressStubs;
use WP2\Update\Admin\Assets\Manager;
use WP2\Update\Config;

beforeEach(function () {
    WordPressStubs::reset();
});

it('builds localized data from stored apps', function () {
    update_option(Config::OPTION_APPS, [
        [
            'id' => 'app-1',
            'name' => 'First',
            'status' => 'pending',
            'account_type' => 'user',
            'managed_repositories' => ['owner/repo'],
        ],
    ]);

    $manifest = [
        'assets/scripts/admin-main.js' => ['file' => 'admin-main.js'],
        'assets/styles/admin-main.scss' => ['file' => 'admin-style.css'],
    ];

    $reflection = new ReflectionClass(Manager::class);
    $method     = $reflection->getMethod('localize_script_data');
    $method->setAccessible(true);
    $method->invoke(null, 'wp2-update-admin-main', $manifest);

    $localized = WordPressStubs::$localizedScripts['wp2-update-admin-main']['wp2UpdateData'] ?? null;

    expect($localized)->not->toBeNull();
    expect($localized['apps'][0]['id'])->toBe('app-1');
    expect($localized['selectedAppId'])->toBe('app-1');
});
