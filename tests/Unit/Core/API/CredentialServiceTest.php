<?php

use Tests\Helpers\WordPressStubs;
use WP2\Update\Config;
use WP2\Update\Core\API\CredentialService;
use WP2\Update\Core\AppRepository;

beforeEach(function () {
    WordPressStubs::reset();
    update_option(Config::OPTION_APPS, []);
});

it('stores credentials using the fallback key', function () {
    $repository = new AppRepository();
    $service    = new CredentialService($repository);

    $result = $service->store_app_credentials([
        'name'            => 'Fresh App',
        'app_id'          => 1234,
        'installation_id' => 0,
        'slug'            => 'fresh-app',
        'private_key'     => "-----BEGIN PRIVATE KEY-----\nkey\n-----END PRIVATE KEY-----",
        'webhook_secret'  => 'secret',
    ]);

    expect($result['status'])->toBe('pending');

    $fetched = $service->get_stored_credentials($result['id']);
    expect($fetched['name'])->toBe('Fresh App');
    expect($fetched['private_key'])->toContain('KEY-----');
});

it('clears unreadable legacy credentials automatically', function () {
    update_option(Config::OPTION_APPS, [
        'legacy' => [
            'id' => 'legacy',
            'name' => 'Legacy App',
            'private_key' => 'invalid',
            'webhook_secret' => 'invalid',
            'encryption_key' => 'ignored-key',
        ],
    ]);

    $repository = new AppRepository();
    $service    = new CredentialService($repository);

    $credentials = $service->get_stored_credentials('legacy');
    expect($credentials)->toBe([]);

    $stored = $repository->find('legacy');
    expect($stored['private_key'])->toBe('');
    expect($stored['installation_id'])->toBe(0);
    expect($stored['status'])->toBe('pending');
    expect(isset($stored['encryption_key']))->toBeFalse();
});

it('fetches credentials for a valid app', function () {
    $repository = new AppRepository();
    $service    = new CredentialService($repository);

    // Set a valid encryption key
    $service->set_custom_encryption_key('test-encryption-key-123');

    $appUid = 'valid-app';
    $repository->save([
        'id' => $appUid,
        'name' => 'Valid App',
        'private_key' => $service->test_encrypt_secret('valid-key', 'test-encryption-key-123'),
        'installation_id' => 12345,
    ]);

    $credentials = $service->get_stored_credentials($appUid);
    expect($credentials['name'])->toBe('Valid App');
    expect($credentials['installation_id'])->toBe(12345);
});
