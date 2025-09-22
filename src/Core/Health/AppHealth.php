<?php

namespace WP2\Update\Core\Health;

use Github\Exception\ExceptionInterface;
use WP2\Update\Core\API\Service as GitHubService;
use WP2\Update\Utils\SharedUtils;
use RuntimeException;
use OpenSSLAsymmetricKey;

use WP2\Update\Utils\Logger;

/**
 * Validates the health of a wp2_github_app configuration.
 *
 * This class performs a multi-step check to ensure that the application
 * can not only authenticate with GitHub but also has the necessary permissions
 * to perform its duties, such as reading repository contents.
 */
class AppHealth {

    /**
     * Post meta key for storing the health status.
     * @var string
     */
    const META_KEY_STATUS = '_health_status';

    /**
     * Post meta key for storing the health message.
     * @var string
     */
    const META_KEY_MESSAGE = '_health_message';

    /**
     * Post meta key for storing the last checked timestamp.
     * @var string
     */
    const META_KEY_LAST_CHECKED = '_last_checked_timestamp';

    /**
     * The post ID of the wp2_github_app CPT.
     * @var int
     */
    private int $app_post_id;

    /**
     * The GitHub API service.
     * @var GitHubService
     */
    private GitHubService $github_service;

    /**
     * The required GitHub App permissions for the plugin to function.
     * The keys are the permission names and values are the required access level (e.g., 'read', 'write').
     * @var array<string, string>
     */
    private array $required_permissions;

    /**
     * @param int           $app_post_id The post ID for the wp2_github_app.
     * @param GitHubService $github_service The service for GitHub API interactions.
     * @param array         $required_permissions Permissions to validate (e.g., ['contents' => 'read']).
     */
    public function __construct(int $app_post_id, GitHubService $github_service, array $required_permissions = []) {
        $this->app_post_id = $app_post_id;
        $this->github_service = $github_service;
        $this->required_permissions = $required_permissions;
    }

    /**
     * Runs all health checks for the GitHub App, from basic configuration to API permissions.
     */
    public function run_checks(): void {
        // 1. Basic WordPress Configuration Check
        $app_slug = get_post_field('post_name', $this->app_post_id);
        if (empty($app_slug)) {
            $this->update_status('error', 'Configuration error: The App Connection post is missing a valid slug (post_name). Please re-save the post.');
            return;
        }

        // Debugging: Log the app_slug retrieved from the database
        Logger::log_debug( sprintf( 'Running health checks for app post %d (slug "%s").', $this->app_post_id, $app_slug ), 'health' );

        // 2. Local Credential Validation
        // Fetch the WordPress Post ID and GitHub App ID separately
        $wp_post_id = $this->app_post_id;
        $github_app_id = get_post_meta($wp_post_id, '_wp2_app_id', true);

        if (empty($github_app_id)) {
            $this->update_status('error', 'Configuration error: The GitHub App ID is missing.');
            return;
        }

        $installation_id = get_post_meta($this->app_post_id, '_wp2_installation_id', true);
        if (empty($installation_id)) {
            $this->update_status('error', 'Configuration error: The Installation ID is missing.');
            return;
        }

        $encrypted_key = get_post_meta($this->app_post_id, '_wp2_private_key_content', true);
        if (empty($encrypted_key)) {
            $this->update_status('error', 'Configuration error: The Private Key is missing.');
            return;
        }

        // 3. Decryption and Key Integrity Check
        $private_key = null;
        try {
            $private_key = SharedUtils::decrypt($encrypted_key);
        } catch (RuntimeException $e) {
            $this->update_status('error', 'Decryption failed. The private key could not be decrypted. Please re-save the key and try again. See the event log for more details.');
            return;
        }

        if (false === openssl_pkey_get_private((string) $private_key)) {
             $this->update_status('error', 'Private Key is Invalid. The saved private key is corrupted or not a valid PEM format key. Please re-upload the correct .pem file.');
            return;
        }
        
        // 4. Create Debug Information. This will be added to error messages.
        $debug_info = sprintf(
            ' Attempting to connect with GitHub App ID: "%s" and Installation ID: "%s".',
            esc_html($github_app_id),
            esc_html($installation_id)
        );

        // 5. Authentication and Client Initialization Check
        $client = $this->github_service->get_client($app_slug);

        if (!$client) {
            $this->update_status('error', 'Authentication failed with GitHub, which usually indicates "Bad credentials".' . $debug_info . ' Please verify these values are correct.');
            return;
        }

        // 6. API Connectivity and App Identity Verification
        try {
            $response = $client->apps()->getAuthenticatedApp();

            if (empty($response['id'])) {
                $this->update_status('error', 'API validation failed. The authenticated app could not be verified with GitHub. The credentials may be for a different app.' . $debug_info);
                return;
            }

            if ((string) $response['id'] !== (string) $github_app_id) {
                $this->update_status('error', 
                    sprintf(
                        'Credential mismatch. Successfully authenticated as App "%s" (ID: %s), but settings are configured for GitHub App ID %s.',
                        $response['name'] ?? 'Unknown',
                        $response['id'],
                        esc_html($github_app_id)
                    )
                );
                return;
            }

            // 7. Permissions Validation
            if (!empty($this->required_permissions)) {
                $granted_permissions = $response['permissions'] ?? [];
                $missing_permissions = $this->get_missing_permissions($granted_permissions);

                if (!empty($missing_permissions)) {
                    $message = sprintf(
                        'Warning: Authentication successful, but the app is missing required permissions: %s. Please review and accept the latest permissions for this app on GitHub.',
                        implode(', ', $missing_permissions)
                    );
                    $this->update_status('warning', $message);
                    return;
                }
            }

        } catch (ExceptionInterface $e) {
            $error_message = 'GitHub API error: ' . $e->getMessage();
            if (strpos($e->getMessage(), 'Bad credentials') !== false) {
                $server_time = current_time('mysql');
                $error_message = 'Authentication failed: GitHub rejected the credentials as invalid.' . $debug_info . ' A common cause for this is a server time mismatch. Your server time: ' . $server_time . '. Please ensure your server\'s clock is synchronized with an NTP service.';
            }
            $this->update_status('error', $error_message);
            return;
        }

        // 8. Success
        $this->update_status('ok', 'Successfully authenticated with GitHub and verified API connectivity and permissions.');
    }

    /**
     * Compares granted permissions with required permissions and returns a list of missing ones.
     *
     * @param array $granted_permissions The permissions array from the GitHub API response.
     * @return string[] A list of missing permissions formatted for display.
     */
    private function get_missing_permissions(array $granted_permissions): array {
        $missing = [];
        foreach ($this->required_permissions as $name => $level) {
            if (!isset($granted_permissions[$name]) || $granted_permissions[$name] !== $level) {
                $missing[] = "{$name}: {$level}";
            }
        }
        return $missing;
    }

    /**
     * Updates the health status meta fields for the app post.
     *
     * @param string $status The health status ('ok', 'warning', 'error').
     * @param string $message A user-friendly message describing the status.
     */
    private function update_status(string $status, string $message): void {
        update_post_meta($this->app_post_id, self::META_KEY_STATUS, $status);
        update_post_meta($this->app_post_id, self::META_KEY_MESSAGE, $message);
        update_post_meta($this->app_post_id, self::META_KEY_LAST_CHECKED, time());
    }
}

