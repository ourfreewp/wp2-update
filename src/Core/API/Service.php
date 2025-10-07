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
	 * @var string The class name for WP_Query, allowing for dependency injection.
	 */
	private string $wpQueryClass;

	/**
	 * Constructor.
	 *
	 * Automatically clears the client and credentials cache on instantiation
	 * to handle any bad data.
	 *
	 * @param string $wpQueryClass The class name for WP_Query, defaulting to the global WP_Query.
	 */
	public function __construct(string $wpQueryClass = WP_Query::class) {
		$this->wpQueryClass = $wpQueryClass;
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
			return null;
		}

		try {
			$client = new GitHubClient();
			$client->authenticate($credentials['private_key'], AuthMethod::JWT);
			self::$client_cache[ $app_slug ] = $client;
			return $client;
		} catch ( ExceptionInterface $e ) {
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
			return [ 'ok' => false, 'error' => 'Client not available' ];
		}

		try {
			$data = $client->api('repo')->$method($path, $params, $headers);
			return [ 'ok' => true, 'data' => $data ];
		} catch ( ExceptionInterface $e ) {
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
	 * @param string $app_slug
	 * @return array{app_id:string,installation_id:string,private_key:string}|null
	 */
	protected function get_app_credentials( string $app_slug ): ?array {
		if ( isset( self::$credentials_cache[ $app_slug ] ) ) {
			return self::$credentials_cache[ $app_slug ];
		}

		$query = new $this->wpQueryClass([
			'post_type'      => 'wp2_github_app',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => [
				[
					'key'     => '_wp2_app_slug',
					'value'   => $app_slug,
					'compare' => '=',
				],
			],
		]);

		if ( ! $query->have_posts() ) {
			return null;
		}

		$post_id = (int) $query->posts[0];

		$app_id          = (string) get_post_meta( $post_id, '_wp2_app_id', true );
		$installation_id = (string) get_post_meta( $post_id, '_wp2_installation_id', true );
		$private_key     = (string) get_post_meta( $post_id, '_wp2_private_key', true );

		self::$credentials_cache[ $app_slug ] = compact( 'app_id', 'installation_id', 'private_key' );
		return self::$credentials_cache[ $app_slug ];
	}

	/**
	 * Clears the client and credentials cache.
	 */
	public static function clear_cache(): void {
		self::$client_cache = [];
		self::$credentials_cache = [];
	}
}