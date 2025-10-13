<?php

namespace Tests\Doubles;

use WP2\Update\Services\Github\ClientService;

class FakeGitHubClientService extends GitHubClientService
{
    public FakeGitHubClient $client;

    public function __construct(FakeGitHubClient $client)
    {
        $this->client = $client;
    }

    public function getInstallationClient(?string $appUid = null): ?ClientService {
        return $this->client;
    }
}
