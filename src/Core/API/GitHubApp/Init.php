<?php

namespace WP2\Update\Core\API\GitHubApp;

if (!defined('ABSPATH')) {
    exit;
}

use WP2\Update\Core\API\Service;

/**
 * Lightweight facade around the GitHub service.
 */
class Init
{
    private Service $service;

    public function __construct(Service $service)
    {
        $this->service = $service;
    }

    /**
     * Whether credentials look complete enough to attempt API calls.
     */
    public function has_credentials(): bool
    {
        $credentials = $this->service->get_stored_credentials();

        return !empty($credentials['app_id'])
            && !empty($credentials['installation_id'])
            && !empty($credentials['private_key']);
    }

    /**
     * Returns a simple status array for display.
     *
     * @return array{connected:bool,message:string}
     */
    public function get_connection_status(): array
    {
        if ($this->has_credentials()) {
            return [
                'connected' => true,
                'message'   => __('Credentials have been saved. Run a manual update check to verify connectivity.', 'wp2-update'),
            ];
        }

        return [
            'connected' => false,
            'message'   => __('Enter your GitHub App credentials to enable update checks.', 'wp2-update'),
        ];
    }

    /**
     * Attempt to authenticate against GitHub and return a status object.
     *
     * @return array{success:bool,message:string}
     */
    public function test_connection(): array
    {
        return $this->service->test_connection();
    }

    /**
     * Saves the GitHub App manifest configuration.
     *
     * @param string $appName The name of the GitHub App.
     * @param string|null $organization The optional organization name.
     * @return void
     */
    public function saveManifestConfig(string $appName, ?string $organization): void
    {
        // Example logic to save the manifest configuration
        update_option('wp2_github_app_name', $appName);
        update_option('wp2_github_organization', $organization);
    }
}
