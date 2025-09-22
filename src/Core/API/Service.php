<?php
namespace WP2\Update\Core\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Github\AuthMethod;
use Github\Client as GitHubClient;
use Github\Exception\ExceptionInterface;
use Github\ResultPager;
use WP2\Update\Utils\Logger;
use WP2\Update\Utils\SharedUtils;
use WP_Error;

/**
 * Manages the GitHub API client and authentication.
 */
final class Service {

	/** @var array<string,GitHubClient> */
	private static array $client_cache = [];

	/** @var array<string,array|null> */
	private static array $credentials_cache = [];

	/**
	 * Constructor.
	 *
	 * Automatically clears the client and credentials cache on instantiation
	 * to handle any bad data.
	 */
	public function __construct() {
		// Automatically clear cache on instantiation to handle bad data.
		self::clear_cache();
	}

	/**
	 * @param string $app_slug
	 * @return GitHubClient|null
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

			$jwt = $this->create_app_jwt( $credentials['app_id'], $credentials['private_key'] );
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
	 * @param string              $app_slug
	 * @param string              $method
	 * @param string              $path
	 * @param array<string,mixed> $params
	 * @return array{ok:bool,data?:mixed,error?:string}
	 */
	public function call( string $app_slug, string $method, string $path, array $params = [] ): array {
		$client = $this->get_client( $app_slug );
		if ( ! $client ) {
			return [ 'ok' => false, 'error' => 'Client not authenticated' ];
		}

		try {
			$this->debug_log( sprintf( "GitHub Service: %s %s", strtoupper( $method ), $path ) );

			// Normalize method to lowercase and validate
			$http_method = strtolower($method);
			if ( ! in_array( $http_method, [ 'get', 'post', 'patch', 'put', 'delete' ], true ) ) {
				return [ 'ok' => false, 'error' => "Invalid HTTP method: {$method}" ];
			}

			if ( ! empty( $params ) ) {
				$this->debug_log( sprintf( "GitHub Service: Request params keys - %s", implode( ', ', array_keys( $params ) ) ) );
			}

			// Call the GitHub API using the HttpClient directly
			$response = $client->getHttpClient()->{$http_method}( $path, $params );

			// Parse JSON response
			$data = json_decode( (string) $response->getBody(), true );

			$status = method_exists( $response, 'getStatusCode' ) ? $response->getStatusCode() : 'unknown';
			$this->debug_log( sprintf( "GitHub Service: Response received for %s %s (status %s).", strtoupper( $method ), $path, $status ) );
			return [ 'ok' => true, 'data' => $data ];
		} catch (ExceptionInterface $e) {
			Logger::log("GitHub Service: API call failed - Message: " . $e->getMessage(), 'error', 'api');
			return [ 'ok' => false, 'error' => $e->getMessage() ];
		} catch (\Throwable $e) {
			Logger::log("GitHub Service: Unexpected error - Message: " . $e->getMessage(), 'error', 'api');
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
	private function get_app_credentials( string $app_slug ): ?array {
		if ( isset( self::$credentials_cache[ $app_slug ] ) ) {
			$this->debug_credentials($app_slug, self::$credentials_cache[ $app_slug ]);
			return self::$credentials_cache[ $app_slug ];
		}

		$query = new \WP_Query(
			[
				'post_type'      => 'wp2_github_app',
				'name'           => $app_slug,
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'no_found_rows'  => true,
				'fields'         => 'ids',
			]
		);

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
	 * Builds a short-lived JWT for authenticating as a GitHub App.
	 */
	private function create_app_jwt( string $app_id, string $private_key ): string {
		$now      = time();
		$payload  = [
			'iat' => $now - 60,
			'exp' => $now + (10 * 60),
			'iss' => (int) $app_id,
		];
		$header   = [ 'alg' => 'RS256', 'typ' => 'JWT' ];

		$segments = [
			$this->base64_url_encode( wp_json_encode( $header ) ?: '' ),
			$this->base64_url_encode( wp_json_encode( $payload ) ?: '' ),
		];

		$signing_input = implode( '.', $segments );
		$signature     = '';

		if ( ! openssl_sign( $signing_input, $signature, $private_key, OPENSSL_ALGO_SHA256 ) ) {
			throw new \RuntimeException( 'Unable to sign JWT for GitHub App authentication.' );
		}

		$segments[] = $this->base64_url_encode( $signature );

		return implode( '.', $segments );
	}

	/**
	 * Encodes data using base64 URL-safe variant without padding.
	 */
	private function base64_url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
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
            $response = $client->getHttpClient()->get( $url );
            $body = $response->getBody()->getContents();

            $temp_file = wp_tempnam( $url );
            if ( ! $temp_file ) {
                return new \WP_Error( 'temp_file_error', __( 'Failed to create a temporary file.', 'wp2-update' ) );
            }

            file_put_contents( $temp_file, $body );
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
}
