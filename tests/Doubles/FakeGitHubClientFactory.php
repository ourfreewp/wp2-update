<?php

namespace Tests\Doubles;

use WP2\Update\Services\Github\ClientService;

class FakeGitHubClientFactory extends ClientService
{
    public FakeGitHubClient $client;

    public function __construct(FakeGitHubClient $client)
    {
        $this->client = $client;
    }

    public function getInstallationClient(?string $appUid = null, bool $forceRefresh = false): ?\Github\Client {
        return new \Github\Client(); // Returning a compatible instance.
    }
}
