<?php

namespace Tests\Doubles;

use Github\Client;

class FakeGitHubClient extends Client
{
    public int $repositoryCalls = 0;
    private array $repositories;

    public function __construct(array $repositories)
    {
        parent::__construct();
        $this->repositories = $repositories;
    }

    public function currentUser(): self
    {
        return $this;
    }

    public function repositories(): array
    {
        $this->repositoryCalls++;
        return $this->repositories;
    }

    public function apps(): self
    {
        return $this;
    }

    public function listRepositories(): array
    {
        return [
            'repositories' => $this->repositories,
        ];
    }
}
