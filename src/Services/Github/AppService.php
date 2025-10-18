<?php
declare(strict_types=1);

namespace WP2\Update\Services\Github;

defined('ABSPATH') || exit;

use WP2\Update\Services\PackageService;
use WP2\Update\Utils\Cache;
use WP2\Update\Utils\HttpClient;
use WP2\Update\Data\AppData;
use WP2\Update\Utils\Encryption;
use Github\Client as GitHubClient;
use WP2\Update\Utils\Logger;
use WP2\Update\Data\DTO\AppDTO;
use WP2\Update\Config;
use WP2\Update\Services\AppPackageMediator;

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
	private AppPackageMediator $mediator;

	public function __construct(
		ClientService $client_service,
		RepositoryService $repository_service,
		callable $package_service_resolver,
		AppData $app_data,
		Encryption $encryption_service
	) {
		$this->client_service           = $client_service;
		$this->repository_service       = $repository_service;
		$this->package_service_resolver = $package_service_resolver;
		$this->app_data                 = $app_data;
		$this->encryption_service       = $encryption_service;
	}

	private function get_package_service(): PackageService {
		if (isset($this->package_service)) {
			return $this->package_service;
		}
		if (is_callable($this->package_service_resolver)) {
			$this->package_service = ($this->package_service_resolver)();
		}
		return $this->package_service;
	}

	public function setMediator(AppPackageMediator $mediator): void {
        $this->mediator = $mediator;
    }

	/**
	 * Tests the connection for a specific app by attempting an API call.
	 *
	 * @return array{success:bool,message:string}
	 */
	public function test_connection(string $app_id): array {
		$app_id = sanitize_text_field($app_id);
		if (empty($app_id)) {
			throw new \InvalidArgumentException(__('Invalid app ID.', Config::TEXT_DOMAIN));
		}

		Logger::start('app_connection_test');
		Logger::info('Starting connection test.', ['app_id' => $app_id]);

		try {
			$client = $this->client_service->getInstallationClient($app_id);
			if (!$client) {
				Logger::warning('Connection test failed: Unable to get installation client.', ['app_id' => $app_id]);
				return [
					'success' => false,
					'message' => __('Unable to authenticate with GitHub.', Config::TEXT_DOMAIN),
				];
			}

			// Use currentUser()->show() to test the connection
			$client->currentUser()->show();

			Logger::info('Connection test successful.', ['app_id' => $app_id]);
			Logger::stop('app_connection_test');
			return [
				'success' => true,
				'message' => __('Connection to GitHub succeeded.', Config::TEXT_DOMAIN),
			];
		} catch (\Throwable $e) {
			Logger::error('Connection test threw an exception.', ['app_id' => $app_id, 'exception' => $e->getMessage()]);
			Logger::stop('app_connection_test');
			return [
				'success' => false,
				'message' => __('Could not connect to GitHub. The token may be invalid or expired.', Config::TEXT_DOMAIN),
			];
		}
	}

	/**
	 * Exchanges a temporary GitHub code for permanent app credentials.
	 *
	 * @param string $code The GitHub code.
	 * @param string $state The state parameter.
	 * @return AppDTO The updated AppDTO object.
	 */
	public function exchange_code_for_credentials(string $code, string $state): AppDTO {
		$code = sanitize_text_field($code);
		$state = sanitize_text_field($state);

		if (empty($code) || empty($state)) {
			throw new \InvalidArgumentException(__('Invalid code or state.', Config::TEXT_DOMAIN));
		}

		Logger::info('Attempting to exchange code for credentials.', ['state' => $state]);
		Logger::start('code_exchange');

		$state_data = Cache::get(Config::TRANSIENT_STATE_PREFIX . $state);
		if (
			!$state_data ||
			!wp_verify_nonce($state_data['nonce'], 'wp2_manifest_' . $state_data['app_id'])
		) {
			Logger::error('Code exchange failed: Invalid state or nonce.', ['state' => $state]);
			throw new \RuntimeException(
				__('Invalid or expired state. Please try connecting again.', Config::TEXT_DOMAIN)
			);
		}
		Cache::delete(Config::TRANSIENT_STATE_PREFIX . $state);

		$response = HttpClient::post("https://api.github.com/app-manifests/{$code}/conversions", []);
		if (!is_array($response)) {
			Logger::error('Code exchange failed: No response from GitHub.', ['state' => $state]);
			throw new \RuntimeException(__('Failed to exchange code with GitHub.', Config::TEXT_DOMAIN));
		}

		Logger::info('Code exchange successful.', ['state' => $state, 'app_id' => $state_data['app_id']]);
		Logger::stop('code_exchange');

		return $this->store_app_credentials($state_data['app_id'], $response);
	}

	/**
	 * Stores credentials received from GitHub.
	 *
	 * @param string $app_id The ID of the app.
	 * @param array $credentials The credentials received from GitHub.
	 * @return AppDTO The updated AppDTO object.
	 */
	public function store_app_credentials(string $app_id, array $credentials): AppDTO {
		Logger::info('Storing app credentials.', ['app_id' => $app_id]);

        $app = $this->app_data->find($app_id) ?? new AppDTO(
            $app_id,
            '',
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s'),
            '',
            'not_configured',
            '',
            [],
            ''
        );

        $app->name = sanitize_text_field($credentials['name'] ?? $app->name);
        $app->installationId = (string) ($credentials['id'] ?? $app->installationId);
        $app->status = !empty($app->installationId) ? 'installed' : $app->status;
        $app->updatedAt = current_time('mysql', true);

        if (!empty($credentials['webhook_secret'])) {
            $app->webhook_secret = trim((string) $credentials['webhook_secret']);
        }

        $incomingPrivateKey = $credentials['private_key'] ?? ($credentials['pem'] ?? null);
        if (!empty($incomingPrivateKey)) {
            $app->private_key = trim((string) $incomingPrivateKey);
        }

        $this->app_data->save($app);
        Cache::delete('wp2_inst_token_' . $app_id);
        return $app;
    }

	public function resolve_app_id(?string $app_id): ?string {
		return $app_id ?: ($this->app_data->find_active_app()['id'] ?? null);
	}

	/**
     * Retrieves the connection status of a GitHub App.
     *
     * @param string $app_id The ID of the app.
     * @return array<string, string> The connection status.
     */
    public function get_connection_status(string $app_id): array {
        $app = $this->app_data->find($app_id);

        if (!$app instanceof AppDTO) {
            return ['status' => 'not_configured'];
        }

        if (empty($app->installationId)) {
            return ['status' => 'app_created'];
        }

        try {
            $client = $this->client_service->getInstallationClient($app_id);
            if (!$client) {
                return ['status' => 'connection_error'];
            }

            $client->currentUser()->show();
            return ['status' => 'installed'];
        } catch (\Throwable $e) {
            return ['status' => 'connection_error'];
        }
    }

	public function create_app_record(string $name): AppDTO {
		if ('' === trim($name)) {
			throw new \InvalidArgumentException('App name cannot be empty.');
		}
		if (!$this->validate_app_name_with_github($name)) {
			throw new \RuntimeException('The app name is invalid or already in use on GitHub.');
		}
        $app = new AppDTO(
            uniqid('app_', true),
            '', // installationId placeholder
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s'),
            $name,
            'inactive',
            '',
            [],
            ''
        );
		$this->app_data->save($app);
		return $app;
	}

	/**
     * Retrieves all apps.
     *
     * @return AppDTO[] List of all AppDTO objects.
     */
    public function get_apps(): array {
        return $this->app_data->get_all();
    }

    /**
     * Retrieves managed repositories by app.
     *
     * @return array<string, string> Mapping of repository slugs to app IDs.
     */
    public function get_managed_repositories_by_app(): array {
        $managed = [];
        foreach ($this->app_data->get_all() as $app) {
            foreach ($app->metadata['managed_repositories'] ?? [] as $repo_slug) {
                $managed[$repo_slug] = $app->id;
            }
        }
        return $managed;
    }

	public function get_app_data(string $app_id): ?array {
		return $this->app_data->find($app_id);
	}

	public function save_app_data(array $app_data): void {
		$appDTO = AppDTO::fromArray($app_data);
		$this->app_data->save($appDTO);
	}

	public function generate_webhook_secret(): string {
		return bin2hex(random_bytes(16));
	}

	public function roll_webhook_secret(string $app_id): string {
		$new_secret = $this->generate_webhook_secret();
		$this->app_data->update_app($app_id, ['webhook_secret' => $new_secret]);
		return $new_secret;
	}

	public function get_installation_client(string $app_id): ?GitHubClient {
		return $this->client_service->getInstallationClient($app_id);
	}

	/**
	 * Validates the app name with GitHub to ensure uniqueness.
	 *
	 * @param string $name The app name to validate.
	 * @return bool True if the app name is valid, false otherwise.
	 */
    private function validate_app_name_with_github(string $name): bool {
        try {
            $appId = $this->resolve_app_id(null);
            if (!$appId) {
                Logger::warning('No active app available to validate name. Skipping remote validation.');
                return true;
            }

            $client = $this->client_service->getClient($appId);
            if (!$client) {
                Logger::warning('Failed to obtain GitHub client for validation.', ['app_id' => $appId]);
                return true;
            }

            $response = $client->getHttpClient()->get('/app/installations');
            $body = [];
            if ($response instanceof \Psr\Http\Message\ResponseInterface) {
                $body = json_decode($response->getBody()->getContents(), true) ?: [];
            } elseif (is_array($response)) {
                $body = $response;
            }

            foreach ($body as $app) {
                if (($app['name'] ?? '') === $name) {
                    return false; // Name already in use.
                }
            }
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to validate app name with GitHub.', ['error' => $e->getMessage()]);
            return false;
        }
    }

	public function generate_manifest_data(
		string $app_id,
		string $name,
		?string $account_type = null,
		?string $org_slug     = null
	): array {
		return [
			'app_id'       => $app_id,
			'name'         => $name,
			'account_type' => $account_type,
			'organization' => $org_slug,
			'permissions'  => [
				'contents' => 'read',
				'metadata' => 'read',
			],
			'events' => ['push', 'pull_request'],
		];
	}

	public function clear_stored_credentials(?string $app_id = null): void {
		if ($app_id) {
			$this->app_data->delete($app_id);
			Cache::delete('wp2_inst_token_' . $app_id);
			return;
		}
		$this->app_data->delete_all();
	}

	/**
     * Stores manual credentials for a GitHub App.
     *
     * @param string $app_id The ID of the app.
     * @param string $private_key The private key for the app.
     * @param string $webhook_secret The webhook secret for the app.
     * @return void
     */
    public function store_manual_credentials(string $app_id, string $private_key, string $webhook_secret): void {
        $app = $this->app_data->find($app_id) ?? new AppDTO(
            $app_id,
            '',
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s'),
            '',
            'inactive',
            '',
            [],
            ''
        );

        $app->webhook_secret = trim($webhook_secret);
        $app->private_key = trim($private_key);
        $app->status = !empty($app->installationId) ? 'installed' : 'inactive';
        $app->updatedAt = current_time('mysql', true);

        $this->app_data->save($app);
        Cache::delete('wp2_inst_token_' . $app_id);
    }

    /**
     * Retrieves all webhook secrets, decrypting them as needed.
     *
     * @return array<string, string> An array mapping app IDs to their decrypted webhook secrets.
     */
    public function get_all_webhook_secrets(): array {
        $secrets = [];
        foreach ($this->app_data->get_all() as $app) {
            if (!empty($app->webhook_secret)) {
                $secrets[$app->id] = $app->webhook_secret;
            }
        }
        return $secrets;
    }

	public function update_app_credentials(string $id, array $credentials): AppDTO {
		$app = $this->app_data->find($id);
		if (!$app) {
			throw new \RuntimeException('App not found.');
		}

        // Update the AppDTO object with new credentials
        foreach ($credentials as $key => $value) {
            if (property_exists($app, $key)) {
                if (in_array($key, ['webhook_secret', 'private_key'], true)) {
                    $value = trim((string) $value);
                }
                $app->$key = $value;
            }
        }

        $this->app_data->save($app);
        return $app;
	}

	/**
     * Assigns a repository to a GitHub App.
     * Ensures no duplicate repositories are added.
     *
     * @param string $app_id The ID of the app.
     * @param string $repository The repository to assign (e.g., 'owner/repo').
     * @throws \InvalidArgumentException If the repository identifier is invalid.
     */
    public function assign_package(string $app_id, string $repository): void {
        $app_id = sanitize_text_field($app_id);
        $repository = sanitize_text_field($repository);

        if (empty($app_id) || !preg_match('/^[a-zA-Z0-9_.-]+\/[a-zA-Z0-9_.-]+$/', $repository)) {
            throw new \InvalidArgumentException(__('Invalid app ID or repository identifier.', Config::TEXT_DOMAIN));
        }

        $app = $this->app_data->find($app_id);
        if (!$app) {
            throw new \RuntimeException(__('App not found.', Config::TEXT_DOMAIN));
        }

        $managed_repositories = $app->metadata['managed_repositories'] ?? [];
        if (!in_array($repository, $managed_repositories, true)) {
            $managed_repositories[] = $repository;
            $app->metadata['managed_repositories'] = $managed_repositories;
            $this->app_data->save($app);
        }
    }

	/**
     * Retrieves connection data for a specific app.
     *
     * @return AppData
     */
    public function get_connection_data(): AppData {
        return $this->app_data;
    }

	/**
     * Retrieves summaries of all configured GitHub Apps.
     *
     * @return array An array of AppDTO objects representing the apps.
     */
    public function get_app_summaries(): array {
        return $this->app_data->get_all();
    }

	/**
     * Fetches all apps along with their connection statuses in a batch operation.
     *
     * @return array An array of apps with their connection statuses.
     */
    public function get_apps_with_status(): array {
        $apps = $this->app_data->get_all_apps();
        $apps_with_status = [];

        foreach ($apps as $app) {
            $app_array = (array) $app; // Ensure $app is treated as an array
            try {
                $connection_status = $this->test_connection($app_array['id']); // Access array key safely
                $apps_with_status[] = (object) array_merge($app_array, [
                    'status' => $connection_status['success'] ? 'Connected' : 'Error',
                ]);
            } catch (\Throwable $e) {
                $apps_with_status[] = (object) array_merge($app_array, [
                    'status' => 'Error',
                ]);
            }
        }

        return $apps_with_status;
    }

	/**
     * Retrieves all configured apps with their details.
     *
     * @return array An array of apps with their details.
     */
    public function get_all_apps(): array {
        $apps = $this->app_data->get_all();

        // Add additional processing or filtering if needed
        foreach ($apps as $app) {
            $app->status = $this->get_connection_status($app->id)['status'];
        }

        return $apps;
    }
}
