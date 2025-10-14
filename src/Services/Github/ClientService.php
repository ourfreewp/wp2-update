<?php

namespace WP2\Update\Services\Github;

use Github\Client as GitHubClient;
use Github\AuthMethod;
use Github\Exception\ExceptionInterface;
use WP2\Update\Utils\Logger;
use WP2\Update\Utils\Cache;
use WP2\Update\Utils\JWT as JwtService;
use WP2\Update\Data\AppData;
use WP2\Update\Utils\Encryption;

/**
 * Manages the GitHub API client, including authentication and token management.
 */
class ClientService {
    private JwtService $jwtService;
    private AppData $appData;
    private Encryption $encryption;
    private array $installationClients = [];

    public function __construct(JwtService $jwtService, AppData $appData, Encryption $encryption) {
        $this->jwtService = $jwtService;
        $this->appData = $appData;
        $this->encryption = $encryption;
    }

    /**
     * Gets an authenticated GitHub API client for a specific app installation.
     * @param string $app_id The ID of the app.
     * @return GitHubClient|null An authenticated client or null on failure.
     */
    public function getInstallationClient(string $app_id): ?GitHubClient {
        if (!$app_id) {
            Logger::log('WARNING', 'App ID is required to create a GitHub client.');
            return null;
        }

        // Fetch credentials directly without relying on ConnectionService
        $credentials = $this->fetchStoredCredentials($app_id);
        if (!$credentials) {
            Logger::log('ERROR', 'No credentials found for the provided app ID.', ['app_id' => $app_id]);
            return null;
        }

        if (isset($this->installationClients[$app_id])) {
            return $this->installationClients[$app_id];
        }

        $token = $this->getInstallationToken($app_id);
        if (!$token) {
            Logger::log('ERROR', 'Failed to retrieve GitHub installation token for app ' . $app_id);
            return null;
        }

        $client = new GitHubClient();
        $client->authenticate($token, AuthMethod::ACCESS_TOKEN);

        $this->installationClients[$app_id] = $client;

        return $client;
    }

    /**
     * Retrieves a cached installation token or generates a new one.
     * @param string $app_id The app ID for which to get a token.
     * @return string|null The installation token or null on failure.
     */
    public function getInstallationToken(string $app_id): ?string {
        $cache_key = 'wp2_inst_token_' . $app_id;
        $cached_token = Cache::get($cache_key);

        if ($cached_token) {
            return $cached_token;
        }

        $credentials = $this->fetchStoredCredentials($app_id);
        if (empty($credentials['app_id']) || empty($credentials['private_key']) || empty($credentials['installation_id'])) {
            Logger::log('WARNING', "Missing credentials to generate installation token for app {$app_id}.");
            return null;
        }

        $token_data = $this->createInstallationToken($credentials);
        if (!$token_data) {
            return null;
        }

        // Cache the token until 60 seconds before it expires.
        $expires_in = max(1, $token_data['expires'] - time() - 60);
        Cache::set($cache_key, $token_data['token'], $expires_in);

        return $token_data['token'];
    }

    /**
     * Generates a new installation token from the GitHub API.
     * @param array $credentials The app credentials.
     * @return array|null An array with the token and expiry time, or null on failure.
     */
    private function createInstallationToken(array $credentials): ?array {
        $jwt = $this->jwtService->generate_jwt($credentials['app_id'], $credentials['private_key']);
        if (!$jwt) {
            return null;
        }

        $client = new GitHubClient();
        $client->authenticate($jwt, AuthMethod::JWT);

        try {
            $result = $client->apps()->createInstallationToken((int) $credentials['installation_id']);
            if (empty($result['token'])) {
                return null;
            }
            return [
                'token'   => $result['token'],
                'expires' => isset($result['expires_at']) ? strtotime($result['expires_at']) : time() + 3540,
            ];
        } catch (ExceptionInterface $e) {
            Logger::log('ERROR', 'Failed to create installation token: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetches stored credentials for a given app ID.
     * @param string $app_id The ID of the app.
     * @return array|null The credentials or null if not found.
     */
    private function fetchStoredCredentials(string $app_id): ?array {
        $credentials = $this->appData->find($app_id);
        if (!$credentials) {
            Logger::log('ERROR', 'No credentials found for the provided app ID.', ['app_id' => $app_id]);
            return null;
        }

        // Decrypt the private key
        $privateKey = $this->encryption->decrypt($credentials['private_key']);

        return [
            'app_id' => $app_id,
            'private_key' => $privateKey,
            'other_data' => $credentials['other_data'] ?? null,
        ];
    }
}
