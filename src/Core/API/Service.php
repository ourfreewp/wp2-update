<?php
namespace WP2\Update\Core\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Firebase\JWT\JWT;
use Github\AuthMethod;
use Github\Client as GitHubClient;
use Github\Exception\ExceptionInterface;
use Github\ResultPager;
use WP2\Update\Utils\Logger;
use WP2\Update\Utils\SharedUtils;
use WP_Error;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use WP2\Update\Core\API\GitHubApp\Init as GitHubAppInit;
use WP_Query;

/**
 * Manages the GitHub API client and authentication.
 */
class Service {

	/**
	 * Cache for GitHub clients.
	 *
	 * @var array<string,GitHubClient> $client_cache
	 */
	private static array $client_cache = [];

	/**
	 * Cache for GitHub app credentials.
	 *
	 * @var array<string,array|null> $credentials_cache
	 */
	private static array $credentials_cache = [];

	/**
	 * @var RequestFactoryInterface
	 */
	private RequestFactoryInterface $requestFactory;

	/**
	 * @var StreamFactoryInterface
	 */
	private StreamFactoryInterface $streamFactory;

	/**
	 * @var string The class name for WP_Query, allowing for dependency injection.
	 */
	private string $wpQueryClass;

	/**
	 * Constructor.
	 *
	 * Automatically clears the client and credentials cache on instantiation
	 * to handle any bad data.
	 *
	 * @param RequestFactoryInterface $requestFactory
	 * @param StreamFactoryInterface $streamFactory
	 * @param string $wpQueryClass The class name for WP_Query, defaulting to the global WP_Query.
	 */
	public function __construct(RequestFactoryInterface $requestFactory = null, StreamFactoryInterface $streamFactory = null, string $wpQueryClass = WP_Query::class) {
        $this->requestFactory = $requestFactory ?? new class implements RequestFactoryInterface {
            public function createRequest(string $method, $uri): \Psr\Http\Message\RequestInterface {
                throw new \RuntimeException('Mocked RequestFactoryInterface');
            }
        };

        $this->streamFactory = $streamFactory ?? new class implements StreamFactoryInterface {
            public function createStream(string $content = ""): \Psr\Http\Message\StreamInterface {
                throw new \RuntimeException('Mocked StreamFactoryInterface');
            }

            public function createStreamFromFile(string $filename, string $mode = 'r'): \Psr\Http\Message\StreamInterface {
                throw new \RuntimeException('Mocked createStreamFromFile');
            }

            public function createStreamFromResource($resource): \Psr\Http\Message\StreamInterface {
                throw new \RuntimeException('Mocked createStreamFromResource');
            }
        };

        $this->wpQueryClass = $wpQueryClass;

        // Automatically clear cache on instantiation to handle bad data.
        self::clear_cache();
    }

	/**
	 * Retrieves the GitHub client for a specific app slug.
	 *
	 * @param string $app_slug The slug of the GitHub app.
	 * @return GitHubClient|null The GitHub client instance or null if credentials are invalid.
	 */
	public function get_client( string $app_slug ): ?GitHubClient {
		if ( isset( self::$client_cache[ $app_slug ] ) ) {
			return self::$client_cache[ $app_slug ];
		}

		$credentials = $this->get_app_credentials( $app_slug );
		if ( ! $credentials ) {
			Logger::log( "GitHub Service: Could not find or validate credentials for app slug '{$app_slug}'.", 'error', 'api' );
			return null;
		}

		try {
			$client = new GitHubClient();

			$this->debug_log( sprintf( "GitHub Service: Authenticating client for app slug '%s'.", $app_slug ) );

			$jwt = $this->create_app_jwt( $app_slug );
			$client->authenticate( $jwt, null, AuthMethod::JWT );

			$this->debug_log( sprintf( "GitHub Service: JWT generated for app slug '%s'.", $app_slug ) );

			$token_data = $client->apps()->createInstallationToken( $credentials['installation_id'] );

			$expires_at = $token_data['expires_at'] ?? 'unknown';
			$this->debug_log( sprintf( "GitHub Service: Installation token issued for app slug '%s'. Expires at %s.", $app_slug, $expires_at ) );

			$client->authenticate( $token_data['token'], AuthMethod::ACCESS_TOKEN );

			self::$client_cache[ $app_slug ] = $client;

			return $client;

		} catch ( ExceptionInterface $e ) {
			Logger::log( "GitHub Service: Authentication failed for app '{$app_slug}'. Message: " . $e->getMessage(), 'error', 'api' );
			return null;
		}
	}

	/**
	 * Makes an authenticated API call to GitHub using the GitHubClient library.
	 *
	 * @param string              $app_slug The slug of the GitHub app.
	 * @param string              $method The HTTP method (GET, POST, etc.).
	 * @param string              $path The API endpoint path.
	 * @param array<string,mixed> $params The request parameters.
	 * @param array<string,string> $headers Optional headers to include in the request.
	 * @return array{ok:bool,data?:mixed,error?:string} The API response.
	 */
	public function call( string $app_slug, string $method, string $path, array $params = [], array $headers = [] ): array {
		$client = $this->get_client( $app_slug );
		if ( ! $client ) {
			return [ 'ok' => false, 'error' => 'GitHub client not available.' ];
		}

		try {
			$httpClient = $client->getHttpClient();
			$requestFactory = $this->requestFactory; // Assume injected RequestFactoryInterface
			$streamFactory = $this->streamFactory; // Assume injected StreamFactoryInterface

			$url = 'https://api.github.com' . $path;
			$request = $requestFactory->createRequest($method, $url);

			foreach ($headers as $key => $value) {
				$request = $request->withHeader($key, $value);
			}

			if (!empty($params)) {
				$body = $streamFactory->createStream(json_encode($params));
				$request = $request->withBody($body);
			}

			$response = $httpClient->sendRequest($request);
			$data = json_decode($response->getBody()->getContents(), true);

			return [ 'ok' => true, 'data' => $data ];
		} catch ( ExceptionInterface $e ) {
			Logger::log( "GitHub API call failed: " . $e->getMessage(), 'error', 'api' );
			return [ 'ok' => false, 'error' => $e->getMessage() ];
		}
	}

	/**
	 * Writes a debug log entry when debugging is enabled.
	 */
	private function debug_log( string $message ): void {
		Logger::log_debug( $message, 'api' );
	}

	/**
     * Debugging: Log credentials and app_slug.
     */
    private function debug_credentials( string $app_slug, ?array $credentials ): void {
        if ( $credentials ) {
            $this->debug_log( sprintf( "GitHub Service: Credentials loaded for app slug '%s'.", $app_slug ) );
        } else {
            $this->debug_log( sprintf( "GitHub Service: No credentials found for app slug '%s'.", $app_slug ) );
        }
    }

	/**
	 * @param string $app_slug
	 * @return array{app_id:string,installation_id:string,private_key:string}|null
	 */
	protected function get_app_credentials( string $app_slug ): ?array {
		if ( isset( self::$credentials_cache[ $app_slug ] ) ) {
			$this->debug_credentials($app_slug, self::$credentials_cache[ $app_slug ]);
			return self::$credentials_cache[ $app_slug ];
		}

		$query = new $this->wpQueryClass(
			[
				'post_type'      => 'wp2_github_app',
				'name'           => $app_slug,
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'no_found_rows'  => true,
				'fields'         => 'ids',
			]
		);

		$this->debug_credentials($app_slug, ['message' => 'Querying posts for app_slug: ' . $app_slug]);

		if ( ! $query->have_posts() ) {
			self::$credentials_cache[ $app_slug ] = null;
			$this->debug_credentials($app_slug, null);
			return null;
		}

		$post_id = (int) $query->posts[0];

		$app_id          = (string) get_post_meta( $post_id, '_wp2_app_id', true );
		$installation_id = (string) get_post_meta( $post_id, '_wp2_installation_id', true );
		$encrypted_key   = (string) get_post_meta( $post_id, '_wp2_private_key_content', true );

		if ( '' === $app_id || '' === $installation_id || '' === $encrypted_key ) {
			self::$credentials_cache[ $app_slug ] = null;
			$this->debug_credentials($app_slug, null);
			return null;
		}

		try {
			$private_key = SharedUtils::decrypt( $encrypted_key );
		} catch ( \RuntimeException $e ) {
			Logger::log( 'GitHub Service: Failed to decrypt private key. ' . $e->getMessage(), 'error', 'api' );
			self::$credentials_cache[ $app_slug ] = null;
			$this->debug_credentials($app_slug, null);
			return null;
		}

		$credentials = [
			'app_id'          => $app_id,
			'installation_id' => $installation_id,
			'private_key'     => $private_key,
		];

		self::$credentials_cache[ $app_slug ] = $credentials;
		$this->debug_credentials($app_slug, $credentials);
		return $credentials;
	}

	/**
	 * Generates a JWT for GitHub App authentication.
	 *
	 * @param string $app_slug The slug of the GitHub app.
	 * @return string|null The generated JWT or null on failure.
	 */
	protected function create_app_jwt( string $app_slug ): ?string {
		$credentials = $this->get_app_credentials( $app_slug );
		if ( ! $credentials ) {
			Logger::log( "GitHub Service: Missing credentials for app slug '{$app_slug}'.", 'error', 'api' );
			return null;
		}

		Logger::log_debug("Debug: Entering create_app_jwt for app slug '{$app_slug}'.", 'test');
        Logger::log_debug("Debug: Credentials: " . print_r($credentials, true), 'test');

		try {
			$githubAppInit = new GitHubAppInit($this);
			return $githubAppInit->generate_jwt($credentials['private_key'], $credentials['app_id']);
		} catch ( \Exception $e ) {
			Logger::log( "GitHub Service: Failed to generate JWT for app slug '{$app_slug}'. Message: " . $e->getMessage(), 'error', 'api' );
			return null;
		}
	}

	/**
     * Clears the client and credentials cache.
     */
    public static function clear_cache(): void {
        self::$client_cache = [];
        self::$credentials_cache = [];
    }

	/**
     * Fetches all paginated repositories for a GitHub App.
     *
     * @param string $app_slug
     * @param string $context
     * @param string $method
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function fetch_all_paginated(string $app_slug, string $context, string $method, array $params = []): array {
        $client = $this->get_client($app_slug);
        if (!$client) {
            Logger::log("GitHub Service: Client not authenticated for app slug '{$app_slug}'.", 'error', 'api');
            return [];
        }

        try {
            $pager = new ResultPager($client);
            $api = $client->{$context}();
            $results = $pager->fetchAll($api, $method, $params);

            Logger::log("GitHub Service: Successfully fetched paginated results for app slug '{$app_slug}'.", 'info', 'api');
            return $results;
        } catch (ExceptionInterface $e) {
            Logger::log("GitHub Service: Failed to fetch paginated results for app slug '{$app_slug}'. Message: " . $e->getMessage(), 'error', 'api');
            return [];
        }
    }

	/**
     * Downloads a file from GitHub to a temporary location.
     *
     * @param string $app_slug The app slug for authentication.
     * @param string $url The URL of the file to download.
     * @return string|WP_Error The path to the temporary file or a WP_Error on failure.
     */
    public function download_to_temp_file( string $app_slug, string $url ) {
        $client = $this->get_client( $app_slug );
        if ( ! $client ) {
            return new \WP_Error( 'github_client_error', __( 'Failed to authenticate GitHub client.', 'wp2-update' ) );
        }

        try {
            Logger::log_debug( 'GitHub Service: Downloading package via authenticated client. URL: ' . $url, 'api' );

            $response = $client->getHttpClient()->get( $url, [ 'headers' => [ 'Accept' => 'application/octet-stream' ] ] );
            $status   = method_exists( $response, 'getStatusCode' ) ? $response->getStatusCode() : 0;

            if ( 200 !== $status ) {
                Logger::log( 'GitHub Service: Package download returned unexpected status ' . $status, 'error', 'api' );
                return new \WP_Error( 'github_download_http_error', sprintf( __( 'GitHub responded with HTTP %d while downloading the package.', 'wp2-update' ), $status ) );
            }

            $body = $response->getBody()->getContents();

            $temp_file = wp_tempnam( $url );
            if ( ! $temp_file ) {
                return new \WP_Error( 'temp_file_error', __( 'Failed to create a temporary file.', 'wp2-update' ) );
            }

            file_put_contents( $temp_file, $body );

            Logger::log_debug( 'GitHub Service: Package downloaded to ' . $temp_file, 'api' );

            return $temp_file;
        } catch ( ExceptionInterface $e ) {
            Logger::log( 'GitHub Service: File download failed - ' . $e->getMessage(), 'error', 'api' );
            return new \WP_Error( 'download_error', $e->getMessage() );
        }
    }

	/**
     * Test GitHub API connection by retrieving app details.
     *
     * @param string $app_slug
     * @return array{success:bool,data?:mixed,error?:string}
     */
    public function test_github_connection( string $app_slug ): array {
        $client = $this->get_client( $app_slug );
        if ( ! $client ) {
            return [ 'success' => false, 'error' => 'Client not authenticated' ];
        }

        try {
            $response = $client->apps()->getAuthenticatedApp();
            return [ 'success' => true, 'data' => $response ];
        } catch ( ExceptionInterface $e ) {
            Logger::log( "GitHub Service: Connection test failed - Message: " . $e->getMessage(), 'error', 'api' );
            return [ 'success' => false, 'error' => $e->getMessage() ];
        }
    }

	/**
     * Fetches details for a specific repository.
     *
     * @param string $app_slug The slug of the GitHub App.
     * @param string $repo_slug The slug of the repository.
     *
     * @return array|null The repository details, or null on failure.
     */
    public function fetch_repository(string $app_slug, string $repo_slug): ?array {
        $client = $this->get_client($app_slug);
        if (!$client) {
            Logger::log("Failed to fetch repository: GitHub client not available for app '{$app_slug}'.", 'error', 'api');
            return null;
        }

        try {
            $repo_data = $client->repo()->showById($repo_slug);
            Logger::log("Successfully fetched repository '{$repo_slug}' for app '{$app_slug}'.", 'info', 'api');
            return $repo_data;
        } catch (ExceptionInterface $e) {
            Logger::log("Error fetching repository '{$repo_slug}' for app '{$app_slug}': " . $e->getMessage(), 'error', 'api');
            return null;
        }
    }

	/**
	 * Retrieves an Installation Access Token for a GitHub App.
	 *
	 * @param string $app_slug The slug of the GitHub app.
	 * @return string|null The Installation Access Token or null on failure.
	 */
	private function get_installation_token( string $app_slug ): ?string {
		$credentials = $this->get_app_credentials( $app_slug );
		if ( ! $credentials ) {
			Logger::log( "GitHub Service: Missing credentials for app slug '{$app_slug}'.", 'error', 'api' );
			return null;
		}

		Logger::log_debug("Debug: Entering get_installation_token for app slug '{$app_slug}'.", 'test');
        Logger::log_debug("Debug: Credentials: " . print_r($credentials, true), 'test');

		try {
			$jwt = $this->create_app_jwt( $app_slug );
			if ( ! $jwt ) {
				return null;
			}

			$githubAppInit = new GitHubAppInit($this);
			return $githubAppInit->get_installation_access_token($jwt, $credentials['installation_id']);
		} catch ( \Exception $e ) {
			Logger::log( "GitHub Service: Failed to retrieve Installation Access Token for app slug '{$app_slug}'. Message: " . $e->getMessage(), 'error', 'api' );
			return null;
		}
	}

	/**
	 * Test-only method to expose private `create_app_jwt` for testing.
	 * @internal
	 */
	public function test_create_app_jwt(string $app_slug): ?string {
		return $this->create_app_jwt($app_slug);
	}

	/**
	 * Test-only method to expose private `get_installation_token` for testing.
	 */
	public function test_get_installation_token(string $app_slug): ?string {
		return $this->get_installation_token($app_slug);
	}
}
