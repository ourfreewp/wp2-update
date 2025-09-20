<?php
namespace WP2\Update\Core\Health;

use WP2\Update\Core\API\Service as GitHubService;

/**
 * Validates the health of a wp2_github_app configuration.
 */
class AppHealth {

    /**
     * The post ID of the wp2_github_app CPT.
     * @var int
     */
    private int $app_post_id;
    private GitHubService $github_service;

    public function __construct(int $app_post_id, GitHubService $github_service) {
        $this->app_post_id = $app_post_id;
        $this->github_service = $github_service;
    }

    /**
     * Runs all health checks for the GitHub App.
     */
    public function run_checks() {
        // 1. Check for required credentials.
        $credentials = $this->get_credentials();
        if (empty($credentials['app_id']) || empty($credentials['installation_id']) || empty($credentials['private_key'])) {
            $this->update_status('error', 'Configuration error: App ID, Installation ID, or Private Key is missing.');
            return;
        }

        // 2. Check authentication and token generation.
        $app_slug = get_post_field('post_name', $this->app_post_id);
        
        // Use the API service directly to test for a token
        $client = $this->github_service->get_client($app_slug);

        if (!$client) {
            $this->update_status('error', 'Authentication failed. Could not generate an installation token. Verify all credentials are correct.');
            return;
        }

        // If all checks pass, the app is healthy.
        $this->update_status('ok', 'Successfully authenticated with GitHub and generated an access token.');
    }

    /**
     * Retrieves the credentials from post meta.
     * @return array
     */
    private function get_credentials(): array {
        return [
            'app_id'          => get_post_meta($this->app_post_id, '_wp2_app_id', true),
            'installation_id' => get_post_meta($this->app_post_id, '_wp2_installation_id', true),
            'private_key'     => get_post_meta($this->app_post_id, '_wp2_private_key', true),
        ];
    }

    /**
     * Updates the health status meta fields for the app post.
     */
    private function update_status(string $status, string $message) {
        update_post_meta($this->app_post_id, '_health_status', $status);
        update_post_meta($this->app_post_id, '_health_message', $message);
        update_post_meta($this->app_post_id, '_last_checked_timestamp', time());
    }
}
