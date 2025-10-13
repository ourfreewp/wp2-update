<?php

use Tests\Doubles\FakeGitHubClient;
use Tests\Doubles\FakeGitHubClientFactory;
use Tests\Helpers\WordPressStubs;
use WP2\Update\Config;
use WP2\Update\Core\AppRepository;
use WP2\Update\Core\API\ConnectionService;
use WP2\Update\Core\API\CredentialService;
use WP2\Update\Services\Github\ClientService;
use WP2\Update\Core\API\RepositoryService;
use WP2\Update\Core\Updates\PackageFinder;

beforeEach(function () {
    WordPressStubs::reset();
    update_option(Config::OPTION_APPS, []);
});

it('assigns repositories to an app without duplicates', function () {
    $appRepository      = new AppRepository();
    $credentialService  = new CredentialService($appRepository);
    $clientFactory      = new ClientService();
    $fakeClient         = new FakeGitHubClient([]);
    $repositoryService  = new RepositoryService($appRepository, new FakeGitHubClientFactory($fakeClient));
    $packageFinder      = new PackageFinder($repositoryService, static fn(string $repoSlug): array => []);
    $connectionService  = new ConnectionService($clientFactory, $credentialService, $packageFinder, $appRepository, $repositoryService);

    $app = $appRepository->save([
        'name' => 'Test App',
        'slug' => 'test-app',
        'managed_repositories' => [],
    ]);

    $connectionService->assign_package($app['id'], 'owner/repo-one');
    $connectionService->assign_package($app['id'], 'owner/repo-one'); // duplicate ignored
    $connectionService->assign_package($app['id'], 'owner/repo-two');

    $apps = $appRepository->all();

    expect($apps[0]['managed_repositories'])->toBe(['owner/repo-one', 'owner/repo-two']);
});

it('rejects invalid repository identifiers when assigning', function () {
    $appRepository      = new AppRepository();
    $credentialService  = new CredentialService($appRepository);
    $repositoryService = new RepositoryService($appRepository);
    $connectionService  = new ConnectionService(
        new ClientService(),
        $credentialService,
        new PackageFinder($repositoryService, static fn(string $repoSlug): array => []),
        $appRepository,
        $repositoryService
    );

    $app = $appRepository->save([
        'name' => 'Test App',
        'slug' => 'test-app',
        'managed_repositories' => [],
    ]);

    $connectionService->assign_package($app['id'], 'owner/repo');

    expect(fn () => $connectionService->assign_package($app['id'], 'invalid-repo'))
        ->toThrow(\InvalidArgumentException::class);
});

it('marks connection as not configured when credentials are missing', function () {
    $appRepository      = new AppRepository();
    $credentialService  = new CredentialService($appRepository);
    $clientFactory      = new ClientService();
    $fakeClient         = new FakeGitHubClient([]);
    $repositoryService  = new RepositoryService($appRepository, new FakeGitHubClientFactory($fakeClient));
    $packageFinder      = new PackageFinder($repositoryService, static fn(string $repoSlug): array => []);
    $connectionService  = new ConnectionService($clientFactory, $credentialService, $packageFinder, $appRepository, $repositoryService);

    $app = $appRepository->save([
        'name' => 'Test App',
        'slug' => 'test-app',
        'managed_repositories' => [],
    ]);

    $status = $connectionService->test_connection($app['id']);

    expect($status['success'])->toBeFalse();
    expect($status['message'])->toBe(__('GitHub credentials are not configured.', 'wp2-update'));
});

it('prompts installation when app is stored without installation id', function () {
    $appRepository     = new AppRepository();
    $fakeClient        = new FakeGitHubClient([]);
    $clientFactory     = new FakeGitHubClientFactory($fakeClient);
    $repositoryService = new RepositoryService($appRepository, $clientFactory);

    $credentialService = new CredentialService($appRepository);
    $credentialService->setRepositoryService($repositoryService);

    $packageFinder     = new PackageFinder($repositoryService, static fn(string $slug): array => []);
    $connectionService = new ConnectionService(
        $clientFactory,
        $credentialService,
        $packageFinder,
        $appRepository,
        $repositoryService
    );

    $app = $credentialService->store_app_credentials([
        'name'            => 'Pending App',
        'app_id'          => 2222,
        'installation_id' => 0,
        'slug'            => 'pending-app',
        'private_key'     => 'dummy-key',
        'webhook_secret'  => 'dummy-secret',
    ]);

    $status = $connectionService->get_connection_status($app['id']);

    expect($status['status'])->toBe('app_created');
    expect($status['details']['app_id'])->toBe(2222);
    expect($status['details']['managed_repositories'])->toBe([]);
});

it('reports installed status with managed packages when connection succeeds', function () {
    $appRepository = new AppRepository();

    $fakeRepositories = [
        ['full_name' => 'owner/repo-one'],
    ];
    $fakeClient    = new FakeGitHubClient($fakeRepositories);
    $clientFactory = new FakeGitHubClientFactory($fakeClient);

    $repositoryService = new RepositoryService($appRepository, $clientFactory);

    $credentialService = new CredentialService($appRepository);
    $credentialService->setRepositoryService($repositoryService);
    $clientFactory->setCredentialService($credentialService);

    WordPressStubs::$plugins = [
        'plugin-one/plugin.php' => [
            'Name'      => 'Plugin One',
            'Version'   => '1.0.0',
            'UpdateURI' => 'owner/repo-one',
        ],
    ];

    $packageFinder     = new PackageFinder($repositoryService, static fn(string $slug): array => []);
    $connectionService = new ConnectionService(
        $clientFactory,
        $credentialService,
        $packageFinder,
        $appRepository,
        $repositoryService
    );

    $app = $credentialService->store_app_credentials([
        'name'            => 'Installed App',
        'app_id'          => 9999,
        'installation_id' => 123456,
        'slug'            => 'installed-app',
        'html_url'        => 'https://github.com/apps/installed-app',
        'private_key'     => 'dummy-key',
        'webhook_secret'  => 'dummy-secret',
    ]);

    $connectionService->assign_package($app['id'], 'owner/repo-one');

    $status = $connectionService->get_connection_status($app['id']);

    expect($status['status'])->toBe('installed');
    expect($status['details']['app_id'])->toBe(9999);
    expect($status['details']['managed_repositories'][0]['repo'])->toBe('owner/repo-one');
    expect($status['details']['managed_repositories'][0]['assigned'])->toBeTrue();
});
