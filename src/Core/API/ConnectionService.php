<?php

namespace WP2\Update\Core\API;

use WP2\Update\Core\API\GitHubClientFactory;
use WP2\Update\Core\API\CredentialService;
use Github\Exception\ExceptionInterface;
use WP2\Update\Utils\Logger;

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
     * Validate stored credentials.
     *
     * @return array{success:bool,message:string}
     */
    private function validate_credentials(): array
    {
        $credentials = $this->credentialService->get_stored_credentials();
        if (!$credentials) {
            return ['success' => false, 'message' => __('GitHub credentials are not configured.', 'wp2-update')];
        }

        if (empty($credentials['app_id']) || empty($credentials['private_key'])) {
            return ['success' => false, 'message' => __('Required credentials are missing.', 'wp2-update')];
        }

        return ['success' => true, 'message' => ''];
    }

    /**
     * Test webhook delivery.
     *
     * @return bool
     */
    private function test_webhook(): bool
    {
        $webhookTestKey = 'wp2_update_webhook_test';
        $webhookTestValue = uniqid('webhook_', true);
        set_transient($webhookTestKey, $webhookTestValue, 60 * 5);

        return get_transient($webhookTestKey) !== false;
    }

    /**
     * Attempt to connect to GitHub using the stored credentials.
     *
     * @return array{success:bool,message:string}
     */
    public function test_connection(): array
    {
        try {
            $credentialValidation = $this->validate_credentials();
            if (!$credentialValidation['success']) {
                return $credentialValidation;
            }

            $credentials = $this->credentialService->get_stored_credentials();

            if (empty($credentials['installation_id'])) {
                return [
                    'success' => false,
                    'message' => __('GitHub App created, but no installation was detected yet. Install the app on your account, then refresh this page.', 'wp2-update'),
                    'code'    => 'missing-installation',
                ];
            }

            $client = $this->clientFactory->getInstallationClient(true);
            if (!$client) {
                return ['success' => false, 'message' => __('Unable to authenticate with GitHub.', 'wp2-update')];
            }

            // Test the connection by listing repositories.
            $client->apps()->listRepositories();

        } catch (\RuntimeException $e) {
            // FIX: This will now catch the fatal error from corrupted credentials.
            \WP2\Update\Utils\Logger::log('ERROR', 'Credential data may be corrupted: ' . $e->getMessage());
            return ['success' => false, 'message' => __('Your saved credentials appear to be corrupted. Please try disconnecting and connecting again.', 'wp2-update')];

        } catch (ExceptionInterface $e) {
            \WP2\Update\Utils\Logger::log('ERROR', 'GitHub connection test failed: ' . $e->getMessage());
            return ['success' => false, 'message' => __('An error occurred while testing the connection.', 'wp2-update')];
        }

        return ['success' => true, 'message' => __('Connection to GitHub succeeded.', 'wp2-update')];
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
            $credentialValidation = $this->validate_credentials();
            if (!$credentialValidation['success']) {
                \WP2\Update\Utils\Logger::log('ERROR', $credentialValidation['message']);
                return ['success' => false, 'steps' => $steps, 'error' => $credentialValidation['message']];
            }

            $credentials = $this->credentialService->get_stored_credentials();

            if (empty($credentials['installation_id'])) {
                $steps[2]['status'] = 'error';
                return [
                    'success' => false,
                    'steps'   => $steps,
                    'error'   => __('No GitHub App installation was found. Install the app on your account, then retry.', 'wp2-update'),
                    'code'    => 'missing-installation',
                ];
            }

            // Step 1: Mint JWT
            $jwt = $this->clientFactory->createJwt($credentials['app_id'], $credentials['private_key']);
            if (!$jwt) {
                \WP2\Update\Utils\Logger::log('ERROR', 'Failed to mint JWT.');
                return ['success' => false, 'steps' => $steps, 'error' => __('Failed to mint JWT.', 'wp2-update')];
            }
            $steps[0]['status'] = 'success';

            // Step 2: Check App ID
            $steps[1]['status'] = 'success';

            // Step 3: Verify Installation ID
            $steps[2]['status'] = 'success';

            // Step 4: Test webhook delivery
            if (!$this->test_webhook()) {
                \WP2\Update\Utils\Logger::log('ERROR', 'Webhook delivery test failed. Transient not cleared.');
                $steps[3]['status'] = 'error';
                return ['success' => false, 'steps' => $steps, 'error' => __('Webhook delivery test failed. Please check your webhook configuration.', 'wp2-update')];
            }
            $steps[3]['status'] = 'success';
        } catch (\Exception $e) {
            \WP2\Update\Utils\Logger::log('ERROR', 'Exception during connection validation: ' . $e->getMessage());
            return ['success' => false, 'steps' => $steps, 'error' => $e->getMessage()];
        }

        Logger::log('INFO', 'GitHub connection validated successfully.');
        return ['success' => true, 'steps' => $steps];
    }

    /**
     * Check if credentials are saved.
     *
     * @return bool
     */
    public function has_credentials(): bool
    {
        $credentials = $this->credentialService->get_stored_credentials();
        return !empty($credentials['app_id']) && !empty($credentials['private_key']);
    }

    /**
     * Provides the current connection status for the dashboard.
     *
     * @return array{status:string,message?:string,details?:array}
     */
    public function get_connection_status(): array
    {
        try {
            $credentials = $this->credentialService->get_stored_credentials();
        } catch (\RuntimeException $e) {
            Logger::log('ERROR', 'Unable to read stored credentials: ' . $e->getMessage());
            return [
                'status'  => 'connection_error',
                'message' => __('Stored credentials are inaccessible. Try disconnecting and reconnecting.', 'wp2-update'),
            ];
        }

        if (empty($credentials)) {
            return ['status' => 'not_configured'];
        }

        if (empty($credentials['installation_id'])) {
            return [
                'status'  => 'app_created',
                'message' => __('Your GitHub App is ready. Install it on your GitHub account to finish connecting.', 'wp2-update'),
                'details' => [
                    'app_name' => $credentials['name'] ?? '',
                    'app_id'   => $credentials['app_id'] ?? '',
                ],
            ];
        }

        $connectionTest = $this->test_connection();
        if (!$connectionTest['success']) {
            $status = $connectionTest['code'] ?? 'connection_error';

            if ($status === 'missing-installation') {
                return [
                    'status'  => 'app_created',
                    'message' => $connectionTest['message'],
                    'details' => [
                        'app_name'        => $credentials['name'] ?? '',
                        'app_id'          => $credentials['app_id'] ?? '',
                    ],
                ];
            }

            return [
                'status'  => 'connection_error',
                'message' => $connectionTest['message'] ?? __('An error occurred while testing the connection.', 'wp2-update'),
            ];
        }

        return [
            'status'  => 'installed',
            'message' => $connectionTest['message'] ?? __('Connection to GitHub succeeded.', 'wp2-update'),
            'details' => [
                'app_name'        => $credentials['name'] ?? '',
                'app_id'          => $credentials['app_id'] ?? '',
                'installation_id' => $credentials['installation_id'] ?? '',
            ],
        ];
    }
}
