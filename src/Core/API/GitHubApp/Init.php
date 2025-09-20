<?php
namespace WP2\Update\Core\API\GitHubApp;

use WP2\Update\Core\API\Service as GitHubService;
use WP2\Update\Utils\Logger;

/**
 * Acts as a facade for all GitHub App-related operations.
 *
 * This class handles the high-level logic and delegates the low-level API
 * interactions to the `GitHubService`. This ensures that other classes don't
 * need to know the complexities of authentication.
 */
class Init {
    /** @var GitHubService The service handling API requests. */
    private GitHubService $service;

    public function __construct(GitHubService $service) {
        $this->service = $service;
    }

    /**
     * A simple wrapper for API calls via the service.
     *
     * @param string $app_slug The app slug to use for authentication.
     * @param string $method The HTTP method (e.g., 'GET', 'POST').
     * @param string $path The API path (e.g., '/repos/owner/repo/releases').
     * @param array<string, mixed> $params The API call parameters.
     * @return array<string, mixed> The response data and status.
     */
    public function gh(string $app_slug, string $method, string $path, array $params = []): array {
        return $this->service->call($app_slug, $method, $path, $params);
    }
    
    /**
     * Gets the current connection status of all configured apps.
     *
     * @return array{
     *     connected: bool,
     *     message: string
     * }
     */
    public function get_connection_status(): array {
        $query = new \WP_Query([
            'post_type'      => 'wp2_github_app',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ]);

        if (!$query->have_posts()) {
            return ['connected' => false, 'message' => 'No GitHub Apps configured.'];
        }

        foreach ($query->posts as $app_post_id) {
            $health_status = get_post_meta($app_post_id, '_health_status', true);
            $health_message = get_post_meta($app_post_id, '_health_message', true);

            if ($health_status === 'ok') {
                return ['connected' => true, 'message' => 'Successfully connected to GitHub.'];
            }
        }

        return ['connected' => false, 'message' => 'No healthy GitHub Apps found. Check Settings > System Health for details.'];
    }

    /**
     * Tests the connection for a specific app configuration.
     *
     * @param string $app_slug The app to test.
     * @return bool True on success, false on failure.
     */
    public function test_connection(string $app_slug): bool {
        Logger::log("Testing connection for app: {$app_slug}", 'info', 'connection-test');
        $response = $this->gh($app_slug, 'GET', '/rate_limit');
        if ($response['ok']) {
            Logger::log("Connection test successful for app: {$app_slug}", 'success', 'connection-test');
            return true;
        }
        Logger::log("Connection test failed for app: {$app_slug}. Error: " . ($response['error'] ?? 'Unknown'), 'error', 'connection-test');
        return false;
    }

    /**
     * Gets the required installation details for the quick-start guide.
     *
     * @return array{
     *     App ID: string,
     *     Installation ID: string,
     *     Private Key: string,
     *     Webhook Secret: string
     * }
     */
    public function get_installation_requirements(): array {
        $app_post = get_posts([
            'post_type'      => 'wp2_github_app',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
        ]);
        $app_id = $app_post[0]->ID ?? null;

        return [
            'App ID'          => get_post_meta($app_id, '_wp2_app_id', true) ?: 'Not set',
            'Installation ID' => get_post_meta($app_id, '_wp2_installation_id', true) ?: 'Not set',
            'Private Key'     => get_post_meta($app_id, '_wp2_private_key', true) ? 'Set' : 'Not set',
            'Webhook Secret'  => get_post_meta($app_id, '_wp2_webhook_secret', true) ? 'Set' : 'Not set',
        ];
    }
}
