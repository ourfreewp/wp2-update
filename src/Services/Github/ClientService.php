<?php
declare(strict_types=1);

namespace WP2\Update\Services\Github;

use Github\Client as GitHubClient;
use Github\AuthMethod;
use Github\Exception\ExceptionInterface;
use WP2\Update\Utils\Cache;
use WP2\Update\Utils\JWT as JwtService;
use WP2\Update\Data\AppData;
use WP2\Update\Utils\Encryption;
use WP2\Update\Utils\Logger;
use WP2\Update\Utils\CustomException;

/**
 * Class ClientService
 *
 * Manages the GitHub API client, including authentication and token management.
 */
class ClientService {
    /**
     * @var JwtService Handles JSON Web Token (JWT) operations.
     */
    private JwtService $jwtService;

    /**
     * @var AppData Provides access to app-related data.
     */
    private AppData $appData;

    /**
     * @var Encryption Handles encryption and decryption of sensitive data.
     */
    private Encryption $encryption;

    /**
     * @var array Stores GitHub API clients for app installations.
     */
    private array $installationClients = [];

    /**
     * Constructor for ClientService.
     *
     * @param JwtService $jwtService Handles JSON Web Token (JWT) operations.
     * @param AppData $appData Provides access to app-related data.
     * @param Encryption $encryption Handles encryption and decryption of sensitive data.
     */
    public function __construct(JwtService $jwtService, AppData $appData, Encryption $encryption) {
        $this->jwtService = $jwtService;
        $this->appData = $appData;
        $this->encryption = $encryption;
    }

    /**
     * Gets an authenticated GitHub API client for a specific app installation.
     *
     * @param string $app_id The ID of the app.
     * @return GitHubClient|null An authenticated client or null on failure.
     */
    public function getInstallationClient(string $app_id): ?GitHubClient {
        if (!$app_id) {
            return null;
        }

        // Fetch credentials directly without relying on ConnectionService
        $credentials = $this->fetchStoredCredentials($app_id);
        if (!$credentials) {
            return null;
        }

        if (isset($this->installationClients[$app_id])) {
            return $this->installationClients[$app_id];
        }

        $token = $this->getInstallationToken($app_id);
        if (!$token) {
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
            Logger::debug('Installation token retrieved from cache.', ['app_id' => $app_id]);
            return $cached_token;
        }

        Logger::info('Generating new installation token.', ['app_id' => $app_id]);
        Logger::start('token_generation');

        $credentials = $this->fetchStoredCredentials($app_id);
        if (empty($credentials['app_id']) || empty($credentials['private_key']) || empty($credentials['installation_id'])) {
            Logger::error('Token generation failed: Missing credentials.', ['app_id' => $app_id]);
            return null;
        }

        $token_data = $this->createInstallationToken($credentials);
        Logger::stop('token_generation');

        if (!$token_data) {
            Logger::error('Token generation failed: Could not create token via GitHub API.', ['app_id' => $app_id]);
            return null;
        }

        $expires_in = max(1, $token_data['expires'] - time() - 60);
        Cache::set($cache_key, $token_data['token'], $expires_in);
        Logger::info('New installation token cached.', ['app_id' => $app_id, 'expires_in' => $expires_in]);

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
            throw new CustomException('Failed to retrieve token.', 500);
        }
    }

    /**
     * Fetches stored credentials for a given app ID.
     * @param string $app_id The ID of the app.
     * @return array|null The credentials or null if not found.
     */
    private function fetchStoredCredentials(string $app_id): ?array {
        $credentials = $this->appData->find($app_id);
        if (!$credentials instanceof AppDTO) {
            Logger::error('No credentials found for the given app ID.', ['app_id' => $app_id]);
            return null;
        }

        try {
            // Attempt to decrypt the private key
            $privateKey = $this->encryption->decrypt($credentials->webhook_secret);
        } catch (\Exception $e) {
            Logger::error('Failed to decrypt private key.', ['app_id' => $app_id, 'error' => $e->getMessage()]);
            return null;
        }

        return [
            'app_id' => $credentials->id,
            'private_key' => $privateKey,
            'other_data' => $credentials->metadata ?? null,
        ];
    }
}
