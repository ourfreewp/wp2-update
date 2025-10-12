<?php

namespace Tests\Doubles;

use Github\Client;
use WP2\Update\Core\API\GitHubClientFactory;

class FakeGitHubClientFactory extends GitHubClientFactory
{
    public FakeGitHubClient $client;

    public function __construct(FakeGitHubClient $client)
    {
        $this->client = $client;
    }

    public function getInstallationClient(?string $appUid = null, bool $forceRefresh = false): ?Client
    {
        return $this->client;
    }
}
