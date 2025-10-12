<?php

use Tests\Helpers\WordPressStubs;
use WP2\Update\Core\AppRepository;
use WP2\Update\Core\API\RepositoryService;
use WP2\Update\Core\Updates\PackageFinder;

beforeEach(function () {
    WordPressStubs::reset();
});

it('detects managed plugins using the Update URI header', function () {
    WordPressStubs::$plugins = [
        'plugin-one/plugin.php' => [
            'Name'       => 'Plugin One',
            'Version'    => '1.0.0',
            'UpdateURI'  => 'owner/repo-one',
        ],
        'plugin-two/plugin.php' => [
            'Name'       => 'Plugin Two',
            'Version'    => '2.0.0',
        ],
    ];

    $finder = new PackageFinder(
        new RepositoryService(new AppRepository()),
        static fn(string $repoSlug): array => []
    );
    $managed = $finder->get_managed_plugins();

    expect($managed)
        ->toHaveCount(1)
        ->and($managed['plugin-one/plugin.php']['repo'])->toBe('owner/repo-one')
        ->and($managed['plugin-one/plugin.php']['type'])->toBe('plugin');
});

it('detects managed themes using the Update URI header', function () {
    WordPressStubs::$themes = [
        'theme-one' => new class {
            public function get(string $field)
            {
                $map = [
                    'Name'      => 'Theme One',
                    'Version'   => '1.2.3',
                    'UpdateURI' => 'owner/theme-one',
                ];

                return $map[$field] ?? null;
            }
        },
        'theme-two' => new class {
            public function get(string $field)
            {
                $map = [
                    'Name'    => 'Theme Two',
                    'Version' => '2.0.0',
                ];

                return $map[$field] ?? null;
            }
        },
    ];

    $finder = new PackageFinder(
        new RepositoryService(new AppRepository()),
        static fn(string $repoSlug): array => []
    );
    $managed = $finder->get_managed_themes();

    expect($managed)
        ->toHaveCount(1)
        ->and($managed['theme-one']['repo'])->toBe('owner/theme-one')
        ->and($managed['theme-one']['type'])->toBe('theme');
});
