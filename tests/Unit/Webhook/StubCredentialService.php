<?php

namespace Tests\Unit\Webhook;

use WP2\Update\Services\Github\AppService;

class StubCredentialService extends AppService
{
    private array $secrets;
    private array $repositories;
    public array $installationUpdates = [];

    public function __construct(array $secrets, array $repositories)
    {
        $this->secrets      = $secrets;
        $this->repositories = $repositories;
    }
}