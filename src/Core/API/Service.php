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

/**
 * Manages the GitHub API client and authentication.
 */
final class Service {

	/** @var array<string,GitHubClient> */
	private static array $client_cache = [];

	/** @var array<string,array|null> */
	private static array $credentials_cache = [];

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

			// Debug: Log the App ID and Installation ID
			Logger::log("GitHub Service: Using App ID: {$credentials['app_id']} and Installation ID: {$credentials['installation_id']}", 'debug', 'api');

			$client->authenticate(
				$credentials['app_id'],
				$credentials['private_key'],
				AuthMethod::JWT
			);

			// Debug: Log JWT generation success
			Logger::log("GitHub Service: JWT generated successfully for App ID: {$credentials['app_id']}", 'debug', 'api');

			$token_data = $client->apps()->createInstallationToken( $credentials['installation_id'] );

			// Debug: Log the installation token
			Logger::log("GitHub Service: Installation token generated successfully: " . substr($token_data['token'], 0, 10) . "...", 'debug', 'api');

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
			$response = $client->getHttpClient()->request(
				$method,
				$path,
				[ 'headers' => [ 'Accept' => 'application/vnd.github.v3+json' ] ] + $params
			);
			$data = json_decode( $response->getBody()->getContents(), true );
			return [ 'ok' => true, 'data' => $data ];
		} catch ( ExceptionInterface $e ) {
			Logger::log( 'API call failed: ' . $e->getMessage(), 'error', 'api' );
			return [ 'ok' => false, 'error' => $e->getMessage() ];
		}
	}

	/**
	 * @param string $app_slug
	 * @return array{app_id:string,installation_id:string,private_key:string}|null
	 */
	private function get_app_credentials( string $app_slug ): ?array {
		if ( isset( self::$credentials_cache[ $app_slug ] ) ) {
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
			return null;
		}

		$post_id = (int) $query->posts[0];

		$app_id          = (string) get_post_meta( $post_id, '_wp2_app_id', true );
		$installation_id = (string) get_post_meta( $post_id, '_wp2_installation_id', true );
		$encrypted_key   = (string) get_post_meta( $post_id, '_wp2_private_key_content', true );

		if ( '' === $app_id || '' === $installation_id || '' === $encrypted_key ) {
			self::$credentials_cache[ $app_slug ] = null;
			return null;
		}

		try {
			$private_key = SharedUtils::decrypt( $encrypted_key );
		} catch ( \RuntimeException $e ) {
			Logger::log( 'GitHub Service: Failed to decrypt private key. ' . $e->getMessage(), 'error', 'api' );
			self::$credentials_cache[ $app_slug ] = null;
			return null;
		}

		$credentials = [
			'app_id'          => $app_id,
			'installation_id' => $installation_id,
			'private_key'     => $private_key,
		];

		self::$credentials_cache[ $app_slug ] = $credentials;
		return $credentials;
	}
}

