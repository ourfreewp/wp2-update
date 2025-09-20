<?php
namespace WP2\Update\Core\API;

use Github\AuthMethod;
use Github\Client as GitHubClient;
use Github\Exception\ExceptionInterface;
use Github\ResultPager;

/**
 * A service class that manages the GitHub API client.
 *
 * This class uses the knplabs/php-github-api library to handle all authentication
 * and API interactions, replacing the custom wp_remote_request logic. It includes
 * caching for credentials and authenticated clients to optimize performance.
 */
class Service {
    /**
     * A static cache of authenticated client instances, keyed by app slug.
     * @var array<string, GitHubClient>
     */
    private static array $client_cache = [];

    /**
     * A static cache for app credentials to reduce database queries.
     * @var array<string, array|null>
     */
    private static array $credentials_cache = [];

    /**
     * Gets a fully authenticated GitHub API client for a specific app configuration.
     *
     * @param string $app_slug The post_name of the wp2_github_app CPT.
     * @return GitHubClient|null An authenticated client or null on failure.
     */
    public function get_client(string $app_slug): ?GitHubClient {
        if (isset(self::$client_cache[$app_slug])) {
            return self::$client_cache[$app_slug];
        }

        $credentials = $this->get_app_credentials($app_slug);
        if (!$credentials) {
            error_log("GitHub Service: Could not find or validate credentials for app slug '{$app_slug}'.");
            return null;
        }

        try {
            $client = new GitHubClient();
            
            // Step 1: Authenticate as the GitHub App itself using a JWT.
            $client->authenticate(
                $credentials['app_id'],
                $credentials['private_key'],
                AuthMethod::JWT
            );

            // Step 2: Create an installation-specific access token.
            $token_data = $client->apps()->createInstallationToken($credentials['installation_id']);
            
            // Step 3: Re-authenticate the client with the final installation token.
            // This is the token that has access to the repositories.
            $client->authenticate($token_data['token'], AuthMethod::ACCESS_TOKEN);

            self::$client_cache[$app_slug] = $client;

            return $client;

        } catch (ExceptionInterface $e) {
            // Catch any exception from the GitHub library (e.g., RuntimeException, ValidationFailedException).
            error_log("GitHub Service: Authentication failed for app '{$app_slug}'. Message: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieves all results for a paginated API call.
     *
     * This is essential for fetching all repositories, as the API limits
     * results per page.
     *
     * @param string $app_slug The slug of the app to use for authentication.
     * @param string $api The API method to call (e.g., 'apps', 'repo').
     * @param string $method The function to execute (e.g., 'listInstallations', 'show').
     * @param array $parameters The parameters for the API call.
     * @return array A flat array of all results from all pages.
     */
    public function fetch_all_paginated(string $app_slug, string $api, string $method, array $parameters = []): array {
        $client = $this->get_client($app_slug);
        if (!$client) {
            return [];
        }

        try {
            $paginator = new ResultPager($client);
            return $paginator->fetchAll($client->api($api), $method, $parameters);
        } catch (ExceptionInterface $e) {
            error_log("GitHub Service: Failed to fetch paginated results for {$api}::{$method}. Message: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Makes a general-purpose API call.
     *
     * @param string $app_slug The app slug to use for authentication.
     * @param string $method The HTTP method (e.g., 'GET', 'POST').
     * @param string $path The API path (e.g., '/repos/owner/repo/releases').
     * @param array $params The API call parameters.
     * @return array The response data and status.
     */
    public function call(string $app_slug, string $method, string $path, array $params = []): array {
        $client = $this->get_client($app_slug);
        if (!$client) {
            return ['ok' => false, 'error' => 'Client not authenticated'];
        }

        try {
            $response = $client->request(
                $method,
                $path,
                array_merge(['headers' => ['Accept' => 'application/vnd.github.v3+json']], $params)
            );
            $data = json_decode($response->getBody()->getContents(), true);
            return ['ok' => true, 'data' => $data];
        } catch (ExceptionInterface $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Retrieves the credentials for a specific app configuration from the database.
     * Results are cached statically to prevent redundant queries within a single request.
     *
     * @param string $app_slug The post_name of the wp2_github_app CPT entry.
     * @return array|null The credentials array or null if not found.
     */
    private function get_app_credentials(string $app_slug): ?array {
        if (isset(self::$credentials_cache[$app_slug])) {
            return self::$credentials_cache[$app_slug];
        }

        $args = [
            'post_type'      => 'wp2_github_app',
            'name'           => $app_slug,
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'no_found_rows'  => true, // Performance optimization
        ];
        $query = new \WP_Query($args);

        if (!$query->have_posts()) {
            self::$credentials_cache[$app_slug] = null;
            return null;
        }
        $post_id = $query->posts[0]->ID;

        $credentials = [
            'app_id'          => get_post_meta($post_id, '_wp2_app_id', true),
            'installation_id' => get_post_meta($post_id, '_wp2_installation_id', true),
            'private_key'     => get_post_meta($post_id, '_wp2_private_key', true),
        ];

        // Validate that all required credentials are present before caching.
        if (empty($credentials['app_id']) || empty($credentials['installation_id']) || empty($credentials['private_key'])) {
            self::$credentials_cache[$app_slug] = null;
            return null;
        }
        
        self::$credentials_cache[$app_slug] = $credentials;
        return $credentials;
    }
}
