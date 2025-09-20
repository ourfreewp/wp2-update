<?php

namespace Tests;

use Mockery;
use WP2\Update\Admin\Views\SystemHealthPage;
use WP2\Update\Core\Connection\Init as Connection;
use WP2\Update\Core\GitHubApp\Init as GitHubApp;
use WP2\Update\Core\Utils\Init as SharedUtils;

class SystemHealthPageTest extends TestCase
{
    public function testGitHubApiStatusIsConnected()
    {
        $githubAppMock = Mockery::mock(GitHubApp::class);
        $githubAppMock->shouldReceive('get_connection_status')->andReturn([
            'connected' => true,
            'message' => 'Connected to GitHub App.',
        ]);

        $connectionMock = Mockery::mock(Connection::class);
        $utilsMock = Mockery::mock(SharedUtils::class);

        $systemHealthPage = new SystemHealthPage(
            $connectionMock,
            $githubAppMock,
            $utilsMock
        );

        $status = $systemHealthPage->get_github_api_status();

        $this->assertEquals('Connected', $status['GitHub App Connection']);
        $this->assertEquals('Connected to GitHub App.', $status['Message']);
    }

    public function testGitHubApiStatusIsNotConnected()
    {
        $githubAppMock = Mockery::mock(GitHubApp::class);
        $githubAppMock->shouldReceive('get_connection_status')->andReturn([
            'connected' => false,
            'message' => 'Not connected to GitHub App.',
        ]);

        $connectionMock = Mockery::mock(Connection::class);
        $utilsMock = Mockery::mock(SharedUtils::class);

        $systemHealthPage = new SystemHealthPage(
            $connectionMock,
            $githubAppMock,
            $utilsMock
        );

        $status = $systemHealthPage->get_github_api_status();

        $this->assertEquals('Not Connected', $status['GitHub App Connection']);
        $this->assertEquals('Not connected to GitHub App.', $status['Message']);
    }
}
