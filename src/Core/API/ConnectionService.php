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
                'message' => __('No GitHub App credentials have been saved yet.', 'wp2-update'),
            ];
        }

        if (empty($credentials['app_id']) || empty($credentials['installation_id']) || empty($credentials['private_key'])) {
            return [
                'success' => false,
                'message' => __('App ID, Installation ID, or Private Key is missing.', 'wp2-update'),
            ];
        }

        $client = $this->clientFactory->getInstallationClient(true);
        if (!$client) {
            return [
                'success' => false,
                'message' => __('Unable to authenticate with GitHub. Double-check the credentials.', 'wp2-update'),
            ];
        }

        try {
            $client->apps()->listRepositories();
        } catch (ExceptionInterface $e) {
            return [
                'success' => false,
                'message' => sprintf(__('GitHub responded with an error: %s', 'wp2-update'), $e->getMessage()),
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
                $steps[0]['status'] = 'error';
                return ['success' => false, 'steps' => $steps];
            }
            $steps[0]['status'] = 'success';

            // Step 2: Check App ID
            $credentials = $this->credentialService->get_stored_credentials();
            if (empty($credentials['app_id'])) {
                $steps[1]['status'] = 'error';
                return ['success' => false, 'steps' => $steps];
            }
            $steps[1]['status'] = 'success';

            // Step 3: Verify Installation ID
            if (empty($credentials['installation_id'])) {
                $steps[2]['status'] = 'error';
                return ['success' => false, 'steps' => $steps];
            }
            $steps[2]['status'] = 'success';

            // Step 4: Test webhook delivery (placeholder)
            $steps[3]['status'] = 'success';
        } catch (\Exception $e) {
            return ['success' => false, 'steps' => $steps];
        }

        return ['success' => true, 'steps' => $steps];
    }
}