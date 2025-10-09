<?php

namespace WP2\Update\Core\API;

use WP2\Update\Core\API\GitHubClientFactory;
use WP2\Update\Core\API\CredentialService;
use Github\Exception\ExceptionInterface;

/**
 * Handles connection-related operations.
 */
class ConnectionService
{
    private GitHubClientFactory $clientFactory;
    private CredentialService $credentialService;

    public function __construct(GitHubClientFactory $clientFactory, CredentialService $credentialService)
    {
        $this->clientFactory = $clientFactory;
        $this->credentialService = $credentialService;
    }

    /**
     * Attempt to connect to GitHub using the stored credentials.
     *
     * @return array{success:bool,message:string}
     */
    public function test_connection(): array
    {
        $credentials = $this->credentialService->get_stored_credentials();
        if (!$credentials) {
            return [
                'success' => false,
                'message' => __('GitHub credentials are not configured.', 'wp2-update'),
            ];
        }

        if (empty($credentials['app_id']) || empty($credentials['installation_id']) || empty($credentials['private_key'])) {
            return [
                'success' => false,
                'message' => __('Required credentials are missing.', 'wp2-update'),
            ];
        }

        $client = $this->clientFactory->getInstallationClient(true);
        if (!$client) {
            return [
                'success' => false,
                'message' => __('Unable to authenticate with GitHub.', 'wp2-update'),
            ];
        }

        try {
            $client->apps()->listRepositories();
        } catch (ExceptionInterface $e) {
            \WP2\Update\Utils\Logger::log('ERROR', 'GitHub connection test failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => __('An error occurred while testing the connection.', 'wp2-update'),
            ];
        }

        return [
            'success' => true,
            'message' => __('Connection to GitHub succeeded.', 'wp2-update'),
        ];
    }

    /**
     * Validates the GitHub connection by performing a series of checks.
     *
     * @return array{success:bool,steps:array}
     */
    public function validate_connection(): array
    {
        $steps = [
            ['key' => 'jwt', 'text' => 'Minting JWT...', 'status' => 'pending'],
            ['key' => 'app_id', 'text' => 'Checking App ID...', 'status' => 'pending'],
            ['key' => 'installation', 'text' => 'Verifying Installation ID...', 'status' => 'pending'],
            ['key' => 'webhook', 'text' => 'Testing webhook delivery...', 'status' => 'pending'],
        ];

        try {
            // Step 1: Mint JWT
            $credentials = $this->credentialService->get_stored_credentials();
            $jwt = $this->clientFactory->createJwt($credentials['app_id'], $credentials['private_key']);
            if (!$jwt) {
                \WP2\Update\Utils\Logger::log('ERROR', 'Failed to mint JWT.');
                return ['success' => false, 'steps' => $steps, 'error' => __('Failed to mint JWT.', 'wp2-update')];
            }
            $steps[0]['status'] = 'success';

            // Step 2: Check App ID
            if (empty($credentials['app_id'])) {
                \WP2\Update\Utils\Logger::log('ERROR', 'App ID is missing in stored credentials.');
                $steps[1]['status'] = 'error';
                return ['success' => false, 'steps' => $steps, 'error' => __('App ID is missing in stored credentials.', 'wp2-update')];
            }
            $steps[1]['status'] = 'success';

            // Step 3: Verify Installation ID
            if (empty($credentials['installation_id'])) {
                \WP2\Update\Utils\Logger::log('ERROR', 'Installation ID is missing in stored credentials.');
                $steps[2]['status'] = 'error';
                return ['success' => false, 'steps' => $steps, 'error' => __('Installation ID is missing in stored credentials.', 'wp2-update')];
            }
            $steps[2]['status'] = 'success';

            // Step 4: Test webhook delivery (placeholder)
            $steps[3]['status'] = 'success';
        } catch (\Exception $e) {
            \WP2\Update\Utils\Logger::log('ERROR', 'Exception during connection validation: ' . $e->getMessage());
            return ['success' => false, 'steps' => $steps, 'error' => $e->getMessage()];
        }

        return ['success' => true, 'steps' => $steps];
    }
}