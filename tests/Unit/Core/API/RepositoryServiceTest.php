<?php

use Tests\Doubles\FakeGitHubClient;
use Tests\Doubles\FakeGitHubClientFactory;
use Tests\Helpers\WordPressStubs;
use WP2\Update\Config;
use WP2\Update\Core\AppRepository;
use WP2\Update\Core\API\RepositoryService;

beforeEach(function () {
    WordPressStubs::reset();
    update_option(Config::OPTION_APPS, []);
});

it('caches repositories per app', function () {
    $repositories = [
        ['full_name' => 'owner/repo-one'],
        ['full_name' => 'owner/repo-two'],
    ];

    $client  = new FakeGitHubClient($repositories);
    $factory = new FakeGitHubClientFactory($client);
    $service = new RepositoryService(new AppRepository(), $factory);

    $firstCall = $service->get_repositories('app-1');
    $secondCall = $service->get_repositories('app-1');

    expect($firstCall)->toBe($repositories);
    expect($secondCall)->toBe($repositories);
    expect($client->repositoryCalls)->toBe(1);
});
