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
        if (!$forceRefresh && $this->installationClient && $this->installationClientExpires && $this->installationClientExpires > (time() + 60)) {
            return $this->installationClient;
        }

        $credentials = $this->credentialService->get_stored_credentials();
        if (!$credentials || empty($credentials['app_id']) || empty($credentials['installation_id']) || empty($credentials['private_key'])) {
            return null;
        }

        $token = $this->createInstallationToken($credentials);

        if (!$token) {
            return null;
        }

        $this->installationClient = new GitHubClient();
        $this->installationClient->authenticate($token['token'], AuthMethod::ACCESS_TOKEN);
        $this->installationClientExpires = time() + 3600; // Tokens are valid for 1 hour

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
        static $cachedToken = null;
        static $tokenExpiry = 0;

        // Return cached token if still valid
        if ($cachedToken && $tokenExpiry > time()) {
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

        // Cache the token and its expiry time
        $cachedToken = $tokenData['token'];
        $tokenExpiry = $tokenData['expires'];

        return $cachedToken;
    }
}