<?php

namespace WP2\Update\Services\Github;

use WP2\Update\Data\ConnectionData;
use WP2\Update\Utils\Cache;
use WP2\Update\Utils\Encryption as EncryptionService;
use WP2\Update\Utils\JWT as JwtService;
use WP2\Update\Utils\Logger;
use WP2\Update\Utils\HttpClient;

/**
 * Handles all logic related to managing GitHub App credentials and the connection flow.
 */
class ConnectionService {
    private ConnectionData $repository;
    private RepositoryService $repositoryService;
    private EncryptionService $encryptionService;
    private JwtService $jwtService;

    public function __construct(
        ConnectionData $repository,
        RepositoryService $repositoryService,
        EncryptionService $encryptionService,
        JwtService $jwtService
    ) {
        $this->repository = $repository;
        $this->repositoryService = $repositoryService;
        $this->encryptionService = $encryptionService;
        $this->jwtService = $jwtService;
    }

    /**
     * Exchanges a temporary GitHub code for permanent app credentials and saves them.
     */
    public function exchange_code_for_credentials(string $code, string $state): array {
        // Retrieve the app ID and nonce from the transient.
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
        $app_data = $this->repository->find($app_id) ?? ['id' => $app_id];

        $app_data = array_merge($app_data, [
            'name'           => sanitize_text_field($credentials['name'] ?? $app_data['name']),
            'app_id'         => absint($credentials['id'] ?? 0),
            'installation_id'=> isset($credentials['installation_id']) ? absint($credentials['installation_id']) : null,
            'slug'           => sanitize_title($credentials['slug'] ?? ''),
            'html_url'       => esc_url_raw($credentials['html_url'] ?? ''),
            'private_key'    => $this->encryptionService->encrypt((string) ($credentials['pem'] ?? '')),
            'webhook_secret' => $this->encryptionService->encrypt((string) ($credentials['webhook_secret'] ?? '')),
            'status'         => isset($credentials['installation_id']) ? 'installed' : 'app_created',
        ]);

        return $this->repository->save($app_data);
    }

    /**
     * Updates the installation ID for an app when an installation webhook is received.
     */
    public function update_installation_id(string $app_id, int $installation_id): void {
        $app = $this->repository->find($app_id);
        if ($app && ($app['installation_id'] ?? null) !== $installation_id) {
            $app['installation_id'] = $installation_id;
            $app['status'] = 'installed';
            $this->repository->save($app);
            Cache::delete('wp2_inst_token_' . $app_id); // Invalidate token cache
        }
    }

    /**
     * Retrieves decrypted credentials for an app.
     */
    public function get_stored_credentials(?string $app_id = null): ?array {
        $app = $this->repository->find($this->resolve_app_id($app_id));
        if (!$app || empty($app['private_key'])) {
            return null;
        }

        $privateKey = $this->encryptionService->decrypt($app['private_key']);
        if (!$privateKey) {
            Logger::log('ERROR', 'Failed to decrypt private key for app ' . $app['id']);
            return null;
        }

        return [
            'id'              => $app['id'],
            'app_id'          => $app['app_id'] ?? '',
            'installation_id' => $app['installation_id'] ?? '',
            'private_key'     => $privateKey,
        ];
    }

    /**
     * Gets all decrypted webhook secrets, indexed by app ID.
     */
    public function get_all_webhook_secrets(): array {
        $secrets = [];
        foreach ($this->repository->all() as $app) {
            if (!empty($app['id']) && !empty($app['webhook_secret'])) {
                $decrypted = $this->encryptionService->decrypt($app['webhook_secret']);
                if ($decrypted) {
                    $secrets[$app['id']] = $decrypted;
                }
            }
        }
        return $secrets;
    }

    /**
     * Deletes credentials for a specific app or all apps.
     */
    public function clear_stored_credentials(?string $app_id = null): void {
        if ($app_id) {
            $this->repository->delete($app_id);
            Cache::delete('wp2_inst_token_' . $app_id);
        } else {
            $this->repository->delete_all();
        }
    }

    /**
     * Resolves which app ID to use.
     */
    public function resolve_app_id(?string $app_id): ?string {
        if ($app_id) {
            return $app_id;
        }
        $active_app = $this->repository->find_active_app();
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
        $app_data = $this->repository->find($app_id) ?? ['id' => $app_id];

        $app_data = array_merge($app_data, [
            'app_id'         => absint($app_id),
            'installation_id'=> absint($installation_id),
            'private_key'    => $this->encryptionService->encrypt($private_key),
            'status'         => 'installed',
        ]);

        $this->repository->save($app_data);
    }

    /**
     * Retrieves the connection status for all apps.
     */
    public function get_connection_status(): array {
        $apps = $this->repository->all();
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
     * Retrieves app summaries for all apps.
     */
    public function get_app_summaries(): array {
        $apps = $this->repository->all();
        $summaries = [];

        foreach ($apps as $app) {
            $summaries[] = [
                'id' => $app['id'],
                'name' => $app['name'] ?? 'Unknown',
                'html_url' => $app['html_url'] ?? '',
                'status' => $app['status'] ?? 'unknown',
            ];
        }

        return $summaries;
    }

    /**
     * Creates a placeholder app with the given name.
     */
    public function create_placeholder_app(string $name): array {
        $app_id = uniqid('app_', true);
        $app_data = [
            'id' => $app_id,
            'name' => $name,
            'status' => 'placeholder',
        ];

        $this->repository->save($app_data);

        return $app_data;
    }

    /**
     * Updates the credentials of an app.
     */
    public function update_app_credentials(string $id, array $params): array {
        $app = $this->repository->find($id);
        if (!$app) {
            throw new \RuntimeException("App with ID {$id} not found.");
        }

        $updated_data = array_merge($app, $params);
        $this->repository->save($updated_data);

        return $updated_data;
    }

    /**
     * Generates manifest data for the GitHub App setup flow.
     *
     * @param string $app_id The app ID.
     * @return array The manifest data including the setup URL and transient data.
     */
    public function generate_manifest_data(string $app_id): array {
        Logger::log('INFO', "Generating manifest data for app: {$app_id}");

        try {
            $nonce = wp_create_nonce("wp2_manifest_{$app_id}");
            $setupUrl = sprintf(
                'https://github.com/settings/apps/new?state=%s',
                urlencode($nonce)
            );

            // Store transient data for the setup flow.
            $transientData = [
                'app_id' => $app_id,
                'nonce' => $nonce,
            ];
            Cache::set("wp2_state_{$nonce}", $transientData, HOUR_IN_SECONDS);

            Logger::log('INFO', "Successfully generated manifest data for app: {$app_id}");

            return [
                'setup_url' => $setupUrl,
                'transient_data' => $transientData,
            ];
        } catch (\Throwable $exception) {
            Logger::log('ERROR', "Failed to generate manifest data for app: {$app_id}. Error: " . $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * Retrieves all connection records.
     *
     * @return array<int, array<string, mixed>> Indexed array of app data.
     */
    public function all(): array {
        return $this->repository->all();
    }

    /**
     * Retrieves the ConnectionData instance.
     *
     * @return ConnectionData The ConnectionData instance.
     */
    public function get_connection_data(): ConnectionData {
        return $this->repository;
    }
}
