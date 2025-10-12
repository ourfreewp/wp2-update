<?php

use Tests\Helpers\WordPressStubs;
use WP2\Update\Core\Updates\PackageService;
use WP2\Update\REST\Controllers\PackagesController;

beforeEach(function () {
    WordPressStubs::reset();
});

afterEach(function () {
    \Mockery::close();
});

it('formats package data for the dashboard sync', function () {
    $service = \Mockery::mock(PackageService::class);
    $service->shouldReceive('sync_packages')->once()->andReturn([
        'packages' => [
            [
                'name'         => 'Plugin One',
                'version'      => '1.0.0',
                'github_data'  => ['latest_release' => 'v1.2.0'],
                'last_updated' => '2024-10-10',
                'stars'        => 10,
                'issues'       => 1,
                'releases'     => [['tag' => 'v1.2.0']],
                'app_slug'     => 'app-1',
                'latest'       => 'v1.2.0',
            ],
        ],
        'unlinked_packages' => [],
    ]);

    $controller = new PackagesController($service);
    $response   = $controller->sync_packages(new WP_REST_Request());
    $data       = $response->get_data();

    expect($response->get_status())->toBe(200);
    expect($data['packages'][0])->toMatchArray([
        'name'         => 'Plugin One',
        'installed'    => '1.0.0',
        'github_data'  => ['latest_release' => 'v1.2.0'],
        'last_updated' => '2024-10-10',
        'stars'        => 10,
        'issues'       => 1,
        'releases'     => [['tag' => 'v1.2.0']],
        'managedBy'    => 'app-1',
        'latest'       => 'v1.2.0',
    ]);
});

it('returns a not found response when no packages exist', function () {
    $service = \Mockery::mock(PackageService::class);
    $service->shouldReceive('get_all_packages')->once()->andReturn([]);

    $controller = new PackagesController($service);
    $response   = $controller->rest_get_packages(new WP_REST_Request());

    expect($response->get_status())->toBe(404);
    $payload = $response->get_data();
    expect($payload['success'])->toBeFalse();
    expect($payload['data']['message'])->toBe('No packages found.');
});

it('assigns a package and returns success', function () {
    $service = \Mockery::mock(PackageService::class);
    $service->shouldReceive('assign_package_to_app')->once()->with('app-1', 'owner/repo');

    $controller = new PackagesController($service);
    $request    = new WP_REST_Request();
    $request->set_param('app_id', 'app-1');
    $request->set_param('repo_id', 'owner/repo');

    $response = $controller->assign_package($request);
    $data     = $response->get_data();

    expect($response->get_status())->toBe(200);
    expect($data['success'])->toBeTrue();
    expect($data['data']['message'])->toBe('Package assigned successfully.');
});

it('propagates assignment errors with failure response', function () {
    $service = \Mockery::mock(PackageService::class);
    $service->shouldReceive('assign_package_to_app')->once()->andThrow(new Exception('failure'));

    $controller = new PackagesController($service);
    $request    = new WP_REST_Request();
    $request->set_param('app_id', 'app-1');
    $request->set_param('repo_id', 'owner/repo');

    $response = $controller->assign_package($request);
    $data     = $response->get_data();

    expect($response->get_status())->toBe(500);
    expect($data['success'])->toBeFalse();
    expect($data['data']['error'])->toBe('failure');
});

it('runs update checks by invoking WordPress helpers', function () {
    $service    = \Mockery::mock(PackageService::class);
    $controller = new PackagesController($service);

    $response = $controller->rest_run_update_check(new WP_REST_Request());
    $data     = $response->get_data();

    expect(WordPressStubs::$pluginUpdateCalls)->toBe(1);
    expect(WordPressStubs::$themeUpdateCalls)->toBe(1);
    expect($data['success'])->toBeTrue();
    expect($data['message'])->toBe('Update check completed successfully.');
});
