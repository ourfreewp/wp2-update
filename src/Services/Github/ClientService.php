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
     * @var Logger Instance of the Logger service.
     */
    private Logger $logger;

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
     * @param Logger $logger Instance of the Logger service.
     */
    public function __construct(JwtService $jwtService, AppData $appData, Encryption $encryption, Logger $logger) {
        $this->jwtService = $jwtService;
        $this->appData = $appData;
        $this->encryption = $encryption;
        $this->logger = $logger;
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
     * Returns a GitHub client instance.
     *
     * @return GitHubClient
     */
    public function getClient(): GitHubClient {
        $client = new GitHubClient();
        $client->authenticate('your-token-here', 'http_token');
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
            $this->logger->debug('Installation token retrieved from cache.', ['app_id' => $app_id]);
            return $cached_token;
        }

        $this->logger->info('Generating new installation token.', ['app_id' => $app_id]);
        $this->logger->start('token_generation');

        $credentials = $this->fetchStoredCredentials($app_id);
        if (empty($credentials['app_id']) || empty($credentials['private_key']) || empty($credentials['installation_id'])) {
            $this->logger->error('Token generation failed: Missing credentials.', [
                'app_id' => $app_id,
                'missing_fields' => [
                    'app_id' => empty($credentials['app_id']),
                    'private_key' => empty($credentials['private_key']),
                    'installation_id' => empty($credentials['installation_id'])
                ]
            ]);
            return null;
        }

        $token_data = $this->createInstallationToken($credentials);
        $this->logger->stop('token_generation');

        if (!$token_data) {
            $this->logger->error('Token generation failed: Could not create token via GitHub API.', ['app_id' => $app_id]);
            return null;
        }

        $expires_in = max(1, $token_data['expires'] - time() - 60);
        Cache::set($cache_key, $token_data['token'], $expires_in);
        $this->logger->info('New installation token cached.', ['app_id' => $app_id, 'expires_in' => $expires_in]);

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
            $this->logger->error('No credentials found for the given app ID.', ['app_id' => $app_id]);
            return null;
        }

        try {
            // Attempt to decrypt the private key
            $privateKey = $this->encryption->decrypt($credentials->private_key);
        } catch (\Exception $e) {
            $this->logger->error('Failed to decrypt private key.', ['app_id' => $app_id, 'error' => $e->getMessage()]);
            return null;
        }

        return [
            'app_id' => $credentials->id,
            'private_key' => $privateKey,
            'installation_id' => $credentials->installationId,
            'other_data' => $credentials->metadata ?? null,
        ];
    }

    /**
     * Checks and handles GitHub API rate limits.
     *
     * @param GitHubClient $client The GitHub client instance.
     * @return bool Returns true if operations can proceed, false if paused.
     */
    public function checkRateLimits(GitHubClient $client): bool {
        try {
            $rateLimitData = $client->getHttpClient()->get('/rate_limit');
            $rateLimit = json_decode($rateLimitData->getBody()->getContents(), true);

            $remaining = $rateLimit['rate']['remaining'] ?? 0;
            $resetTime = $rateLimit['rate']['reset'] ?? time();

            if ($remaining < 100) {
                $waitTime = max(0, $resetTime - time());
                $this->logger->warning('GitHub API rate limit nearing exhaustion. Pausing operations.', [
                    'remaining' => $remaining,
                    'reset_time' => $resetTime,
                    'wait_time' => $waitTime
                ]);

                // Pause operations until reset time
                sleep($waitTime);
                return false;
            }

            return true;
        } catch (ExceptionInterface $e) {
            $this->logger->error('Failed to check GitHub API rate limits.', ['error' => $e->getMessage()]);
            return true; // Allow operations to proceed in case of error
        }
    }
}
