<?php

use Tests\Helpers\WordPressStubs;
use WP2\Update\Webhook\Controller;
use WP2\Update\Core\API\CredentialService;

class StubCredentialService extends CredentialService
{
    private array $secrets;
    private array $repositories;
    public array $installationUpdates = [];

    public function __construct(array $secrets, array $repositories)
    {
        $this->secrets      = $secrets;
        $this->repositories = $repositories;
    }

    public function get_all_webhook_secrets(): array
    {
        return $this->secrets;
    }

    public function update_installation_id(string $appUid, int $installationId): void
    {
        $this->installationUpdates[] = [$appUid, $installationId];
    }

    public function get_managed_repositories(string $appUid): array
    {
        return $this->repositories[$appUid] ?? [];
    }
}

class StubRestRequest extends WP_REST_Request
{
    public function __construct(array $headers, string $body)
    {
        foreach ($headers as $key => $value) {
            $this->set_header($key, $value);
        }
        $this->set_body($body);
    }
}

beforeEach(function () {
    WordPressStubs::reset();
});

it('rejects requests without valid signature', function () {
    $credentialService = new StubCredentialService([], []);
    $controller        = new Controller($credentialService);

    $request = new StubRestRequest([], json_encode(['action' => 'published'], JSON_THROW_ON_ERROR));

    $response = $controller->handle($request);

    expect($response->get_status())->toBe(400);
    expect($response->get_data()['message'])->toBe('Missing payload or signature.');
});

it('stores installation id from installation events', function () {
    $secret            = 'webhook-test';
    $credentialService = new StubCredentialService(['app-1' => $secret], ['app-1' => []]);
    $controller        = new Controller($credentialService);

    $payload = json_encode([
        'installation' => ['id' => 987],
    ], JSON_THROW_ON_ERROR);

    $signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

    $request = new StubRestRequest([
        'X-Hub-Signature-256' => $signature,
        'X-GitHub-Event'      => 'installation',
    ], $payload);

    $response = $controller->handle($request);

    expect($response->get_status())->toBe(200);
    expect($credentialService->installationUpdates)->toBe([
        ['app-1', 987],
    ]);
});

it('clears update caches and fires hook on release events', function () {
    $secret            = 'release-secret';
    $credentialService = new StubCredentialService(
        ['app-1' => $secret],
        ['app-1' => ['owner/repo']]
    );
    $controller        = new Controller($credentialService);

    set_site_transient('update_plugins', ['dummy']);
    set_site_transient('update_themes', ['dummy']);

    $payload = json_encode([
        'action' => 'published',
        'repository' => ['full_name' => 'owner/repo'],
    ], JSON_THROW_ON_ERROR);

    $signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

    $request = new StubRestRequest([
        'X-Hub-Signature-256' => $signature,
        'X-GitHub-Event'      => 'release',
    ], $payload);

    $response = $controller->handle($request);

    expect($response->get_status())->toBe(200);
    expect(get_site_transient('update_plugins'))->toBeFalse();
    expect(get_site_transient('update_themes'))->toBeFalse();
    expect(WordPressStubs::$actionCalls['wp2_update_release_published'][0] ?? [])->toHaveCount(2);
});
