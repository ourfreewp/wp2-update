<?php

namespace WP2\Update\Services\Github;

use WP2\Update\Services\PackageService;
use WP2\Update\Utils\Logger;

/**
 * Handles high-level logic related to GitHub App connections and status.
 */
class AppService {
    private ClientService $clientService;
    private ConnectionService $connectionService;
    private RepositoryService $repositoryService;
    private PackageService $packageService;

    public function __construct(
        ClientService $clientService,
        ConnectionService $connectionService,
        RepositoryService $repositoryService,
        PackageService $packageService
    ) {
        $this->clientService = $clientService;
        $this->connectionService = $connectionService;
        $this->repositoryService = $repositoryService;
        $this->packageService = $packageService;
    }

    /**
     * Tests the connection for a specific app by attempting an API call.
     * @param string $app_id The unique ID of the app to test.
     * @return array{success:bool, message:string}
     */
    public function test_connection(string $app_id): array {
        try {
            $client = $this->clientService->getInstallationClient($app_id);
            if (!$client) {
                return ['success' => false, 'message' => __('Unable to authenticate with GitHub.', \WP2\Update\Config::TEXT_DOMAIN)];
            }

            // A simple, low-cost API call to verify authentication.
            $client->apps()->getAuthenticated();

            return ['success' => true, 'message' => __('Connection to GitHub succeeded.', \WP2\Update\Config::TEXT_DOMAIN)];
        } catch (\Exception $e) {
            Logger::log('ERROR', 'GitHub connection test failed for app ' . $app_id . ': ' . $e->getMessage());
            return ['success' => false, 'message' => __('Could not connect to GitHub. The token may be invalid or expired.', \WP2\Update\Config::TEXT_DOMAIN)];
        }
    }
}
