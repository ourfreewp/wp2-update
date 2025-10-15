<?php

namespace WP2\Update\Services\Github;

use WP2\Update\Services\PackageService;
use WP2\Update\Utils\Logger;
use WP2\Update\Utils\Cache;
use WP2\Update\Utils\HttpClient;
use WP2\Update\Data\AppData;
use WP2\Update\Utils\Encryption;

/**
 * Handles high-level logic related to GitHub App connections and status.
 */
class AppService {
    private ClientService $client_service;
    private RepositoryService $repository_service;
    private PackageService $package_service;
    private AppData $app_data;
    private Encryption $encryption_service;
    private $package_service_resolver;

    public function __construct(
        ClientService $client_service,
        RepositoryService $repository_service,
        callable $package_service_resolver,
        AppData $app_data,
        Encryption $encryption_service
    ) {
        $this->client_service = $client_service;
        $this->repository_service = $repository_service;
        $this->package_service_resolver = $package_service_resolver;
        $this->app_data = $app_data;
        $this->encryption_service = $encryption_service;
    }

    private function get_package_service(): PackageService {
        if (is_callable($this->package_service_resolver)) {
            $this->package_service = ($this->package_service_resolver)();
        }
        return $this->package_service;
    }

    /**
     * Tests the connection for a specific app by attempting an API call.
     * @param string $app_id The unique ID of the app to test.
     * @return array{success:bool, message:string}
     */
    public function test_connection(string $app_id): array {
        try {
            $client = $this->client_service->getInstallationClient($app_id);
            if (!$client) {
                return ['success' => false, 'message' => __('Unable to authenticate with GitHub.', \WP2\Update\Config::TEXT_DOMAIN)];
            }

            // Corrected method call to GitHub API client
            $client->currentUser()->show();

            return ['success' => true, 'message' => __('Connection to GitHub succeeded.', \WP2\Update\Config::TEXT_DOMAIN)];
        } catch (\Exception $e) {
            Logger::log('ERROR', 'GitHub connection test failed: ' . $e->getMessage());
            return ['success' => false, 'message' => __('Could not connect to GitHub. The token may be invalid or expired.', \WP2\Update\Config::TEXT_DOMAIN)];
        }
    }

    /**
     * Exchanges a temporary GitHub code for permanent app credentials and saves them.
     */
    public function exchange_code_for_credentials(string $code, string $state): array {
        $state_data = Cache::get('wp2_state_' . $state);
        if (!$state_data || !wp_verify_nonce($state_data['nonce'], 'wp2_manifest_' . $state_data['app_id'])) {
            throw new \Exception(__('Invalid or expired state. Please try connecting again.', \WP2\Update\Config::TEXT_DOMAIN));
        }
        Cache::delete('wp2_state_' . $state); // One-time use

        $response = HttpClient::post("https://api.github.com/app-manifests/{$code}/conversions", []);
        if (!$response || !is_array($response)) {
            throw new \Exception(__('Failed to exchange code with GitHub.', \WP2\Update\Config::TEXT_DOMAIN));
        }

        return $this->store_app_credentials($state_data['app_id'], $response);
    }

    /**
     * Stores credentials received from GitHub.
     */
    public function store_app_credentials(string $app_id, array $credentials): array {
        $app_data = $this->app_data->find($app_id) ?? ['id' => $app_id];

        // Generate a unique encryption key for the app if not already set.
        $encryption_key = $app_data['encryption_key'] ?? bin2hex(random_bytes(16));
        $app_data['encryption_key'] = $encryption_key;

        $encryption_service = new Encryption($encryption_key);

        $app_data = array_merge($app_data, [
            'name'           => sanitize_text_field($credentials['name'] ?? $app_data['name']),
            'app_id'         => absint($credentials['id'] ?? 0),
            'private_key'    => $encryption_service->encrypt($credentials['private_key'] ?? ''),
            'webhook_secret' => $encryption_service->encrypt($credentials['webhook_secret'] ?? ''),
        ]);

        $this->app_data->save($app_data);
        return $app_data;
    }

    /**
     * Deletes credentials for a specific app or all apps.
     */
    public function clear_stored_credentials(?string $app_id = null): void {
        if ($app_id) {
            $this->app_data->delete($app_id);
            Cache::delete('wp2_inst_token_' . $app_id);
        } else {
            $this->app_data->delete_all();
        }
    }

    /**
     * Resolves which app ID to use.
     */
    public function resolve_app_id(?string $app_id): ?string {
        if ($app_id) {
            return $app_id;
        }
        $active_app = $this->app_data->find_active_app();
        return $active_app['id'] ?? null;
    }

    /**
     * Stores manually entered GitHub App credentials.
     *
     * @param string $app_id The GitHub App ID.
     * @param string $installation_id The installation ID.
     * @param string $private_key The private key.
     * @return void
     */
    public function store_manual_credentials(string $app_id, string $installation_id, string $private_key): void {
        $app_data = $this->app_data->find($app_id) ?? ['id' => $app_id];

        $app_data = array_merge($app_data, [
            'app_id'         => absint($app_id),
            'installation_id'=> absint($installation_id),
            'private_key'    => $this->encryption_service->encrypt($private_key),
        ]);

        $this->app_data->save($app_data);
    }

    /**
     * Retrieves the connection status for all apps.
     */
    public function get_connection_status(): array {
        $apps = $this->app_data->all();
        $statuses = [];

        foreach ($apps as $app) {
            $statuses[] = [
                'id' => $app['id'],
                'name' => $app['name'] ?? 'Unknown',
                'status' => $app['status'] ?? 'unknown',
                'installation_id' => $app['installation_id'] ?? null,
            ];
        }

        return $statuses;
    }

    /**
     * Retrieve all GitHub apps.
     *
     * @return array
     */
    public function get_apps(): array {
        return $this->app_data->all();
    }

    /**
     * Retrieves app data by app ID.
     */
    public function getAppData(string $app_id): ?array {
        return $this->app_data->find($app_id);
    }

    /**
     * Saves app data.
     */
    public function saveAppData(array $app_data): void {
        $this->app_data->save($app_data);
    }

    /**
     * Retrieves managed repositories grouped by app.
     */
    public function getManagedRepositoriesByApp(): array {
        $apps = $this->app_data->all();
        $managed_repos_by_app = [];

        foreach ($apps as $app) {
            foreach ($app['managed_repositories'] ?? [] as $repo_slug) {
                $managed_repos_by_app[$repo_slug] = $app['id'];
            }
        }

        return $managed_repos_by_app;
    }

    /**
     * Factory method to create an instance of AppService.
     *
     * @return AppService
     */
    public static function create(): AppService {
        $jwt_service = new \WP2\Update\Utils\JWT();
        $app_data = new AppData();
        $encryption_service = new Encryption();
        $client_service = new \WP2\Update\Services\Github\ClientService($jwt_service, $app_data, $encryption_service);
        $repository_service = new \WP2\Update\Services\Github\RepositoryService($app_data, $client_service);
        $release_service = new \WP2\Update\Services\Github\ReleaseService($client_service, $app_data);

        $package_service_resolver = function () use ($repository_service, $release_service, $client_service, $app_data) {
            return new PackageService(
                $repository_service,
                $release_service,
                $client_service,
                $this // Corrected to pass the AppService instance
            );
        };

        return new self(
            $client_service,
            $repository_service,
            $package_service_resolver,
            $app_data,
            $encryption_service
        );
    }
}
