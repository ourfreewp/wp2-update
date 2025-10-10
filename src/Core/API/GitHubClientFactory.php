<?php

namespace WP2\Update\Core\API;

use Github\Client as GitHubClient;
use Github\AuthMethod;
use Throwable;
use Firebase\JWT\JWT;
use Github\Exception\ExceptionInterface;
use WP2\Update\Utils\Logger;

/**
 * Factory for creating GitHub API clients.
 */
class GitHubClientFactory
{
    private CredentialService $credentialService;

    private ?GitHubClient $installationClient = null;
    private ?int $installationClientExpires = null;

    public function __construct(CredentialService $credentialService)
    {
        $this->credentialService = $credentialService;
    }

    /**
     * Get an authenticated GitHub installation client.
     *
     * @return GitHubClient
     */
    public function getInstallationClient(bool $forceRefresh = false): ?GitHubClient
    {
        // Use the transient-based caching logic from getInstallationToken
        $token = $this->getInstallationToken();

        if (!$token) {
            Logger::log('ERROR', 'Failed to retrieve GitHub installation token.');
            return null;
        }

        if (!$forceRefresh && $this->installationClient) {
            return $this->installationClient;
        }

        $this->installationClient = new GitHubClient();
        $this->installationClient->authenticate($token, AuthMethod::ACCESS_TOKEN);

        return $this->installationClient;
    }

    /**
     * Builds the JWT used for app authentication.
     */
    public function createJwt(string $appId, string $privateKey): ?string
    {
        $appId = trim($appId);
        $privateKey = trim($privateKey);

        if ('' === $appId || '' === $privateKey) {
            return null;
        }

        $issuedAt = time();
        $payload  = [
            'iat' => $issuedAt,
            'exp' => $issuedAt + 540,
            'iss' => $appId,
        ];

        try {
            return JWT::encode($payload, $privateKey, 'RS256');
        } catch (Throwable $e) {
            Logger::log('ERROR', 'Failed to create JWT: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generates an installation token for the stored credentials.
     */
    public function createInstallationToken(array $credentials): ?array
    {
        $jwt = $this->createJwt($credentials['app_id'], $credentials['private_key']);
        if (!$jwt) {
            return null;
        }

        $client = new GitHubClient();
        $client->authenticate($jwt, AuthMethod::JWT);

        try {
            $result = $client->apps()->createInstallationToken((int) $credentials['installation_id']);
        } catch (ExceptionInterface $e) {
            Logger::log('ERROR', 'Failed to create installation token: ' . $e->getMessage());
            return null;
        }

        if (empty($result['token'])) {
            return null;
        }

        $expiresAt = isset($result['expires_at']) ? strtotime($result['expires_at']) : time() + 3600;

        return [
            'token'   => $result['token'],
            'expires' => $expiresAt,
        ];
    }

    /**
     * Retrieves the installation token for GitHub API authentication.
     *
     * @return string|null The installation token, or null on failure.
     */
    public function getInstallationToken(): ?string
    {
        // Use WordPress transients to cache the token across requests
        $transientKey = 'github_installation_token';
        $cachedToken = get_transient($transientKey);

        if ($cachedToken) {
            return $cachedToken;
        }

        $credentials = $this->credentialService->get_stored_credentials();
        if (empty($credentials['app_id']) || empty($credentials['private_key']) || empty($credentials['installation_id'])) {
            return null;
        }

        $tokenData = $this->createInstallationToken($credentials);
        if (!$tokenData || empty($tokenData['token']) || empty($tokenData['expires'])) {
            return null;
        }

        // Cache the token in a transient with a slightly shorter expiry time
        set_transient($transientKey, $tokenData['token'], $tokenData['expires'] - time() - 60);

        return $tokenData['token'];
    }

    /**
     * Checks the rate limit status for the GitHub API.
     *
     * @return array|null Returns an array with rate limit information or null on failure.
     */
    public function checkRateLimit(): ?array
    {
        $client = $this->getInstallationClient();
        if (!$client) {
            Logger::log('ERROR', 'GitHub client not available for rate limit check.');
            return null;
        }

        try {
            $rateLimit = $client->getHttpClient()->get('/rate_limit');
            $rateLimitData = json_decode($rateLimit->getBody()->getContents(), true);

            $coreLimit = $rateLimitData['rate'] ?? null;

            if ($coreLimit) {
                return [
                    'limit' => $coreLimit['limit'] ?? 0,
                    'remaining' => $coreLimit['remaining'] ?? 0,
                    'reset' => $coreLimit['reset'] ?? 0,
                ];
            }

            return null;
        } catch (ExceptionInterface $e) {
            Logger::log('ERROR', 'Failed to fetch rate limit: ' . $e->getMessage());
            return null;
        }
    }
}