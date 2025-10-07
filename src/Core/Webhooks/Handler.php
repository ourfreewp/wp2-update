<?php
namespace WP2\Update\Core\Webhooks;

use WP2\Update\Core\API\GitHubApp\Init as GitHubApp;
use WP2\Update\Core\Updates\PackageFinder;
use WP2\Update\Utils\Logger;
use WP_REST_Request;

/**
 * Handles incoming webhooks from GitHub.
 *
 * This class validates the request signature, processes the payload, and
 * triggers appropriate actions like package updates or resyncs.
 */
class Handler {
    /** @var GitHubApp The GitHub App handler. */
    private GitHubApp $github_app;

    /** @var PackageFinder The package finder instance. */
    private PackageFinder $package_finder;

    public function __construct(GitHubApp $github_app, PackageFinder $package_finder) {
        $this->github_app = $github_app;
        $this->package_finder = $package_finder;
    }

    /**
     * Handles the webhook request.
     *
     * @param WP_REST_Request $request The REST API request object.
     * @return array
     */
    public function handle_webhook(WP_REST_Request $request): array {
        $payload = $request->get_body();
        $signature = $request->get_header('x_hub_signature_256');
        $event = $request->get_header('x_github_event');

        if (empty($payload) || empty($signature) || empty($event)) {
            Logger::log('Invalid webhook request: Missing payload, signature, or event header.', 'error', 'webhook');
            return ['status' => 'error', 'message' => 'Invalid request'];
        }

        // Validate the IP address of the incoming request.
		$remote_ip = $_SERVER['REMOTE_ADDR'] ?? '';
		// Check for proxy headers before falling back to REMOTE_ADDR
		if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded_ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$remote_ip = trim( end( $forwarded_ips ) );
		} elseif ( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$remote_ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
		}

		if ( ! $this->is_github_ip( $remote_ip ) ) {
			Logger::log("Webhook request from unauthorized IP: {$remote_ip}", 'error', 'webhook');
			return ['status' => 'error', 'message' => 'Unauthorized IP address'];
		}

        // Find the correct app by the webhook secret.
        $app_post_id = $this->find_app_by_webhook_secret($signature, $payload);
        if (!$app_post_id) {
            Logger::log('Webhook validation failed: Signature did not match any configured app.', 'error', 'webhook');
            return ['status' => 'error', 'message' => 'Signature validation failed'];
        }
        
        Logger::log("Webhook received: Event '{$event}' from app ID {$app_post_id}.", 'info', 'webhook');
        
        $this->process_event($event, $payload, $app_post_id);

        return ['status' => 'success', 'message' => 'Webhook received and processed'];
    }

    /**
     * Finds the GitHub App by validating the webhook signature.
     *
     * @param string $signature The signature header from GitHub.
     * @param string $payload The request body.
     * @return int|null The app's post ID or null if no match is found.
     */
    private function find_app_by_webhook_secret(string $signature, string $payload): ?int {
        $query = new \WP_Query([
            'post_type'      => 'wp2_github_app',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ]);

        if ($query->have_posts()) {
            foreach ($query->posts as $app_post_id) {
                $secret = get_post_meta($app_post_id, '_wp2_webhook_secret', true);
                if (empty($secret)) {
                    continue;
                }
                $calculated_signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
                
                Logger::log_debug( sprintf( 'Checking webhook signature for app ID %d', $app_post_id ), 'webhook' );
                
                if (hash_equals($calculated_signature, $signature)) {
                    return $app_post_id;
                }
            }
        }
        return null;
    }

    /**
     * Processes the webhook payload based on the event type.
     *
     * @param string $event The GitHub event type.
     * @param string $payload The webhook payload.
     * @param int $app_post_id The ID of the managing GitHub App.
     */
    private function process_event(string $event, string $payload, int $app_post_id): void {
        $data = json_decode($payload, true);
        
        Logger::log_debug( sprintf( 'Processing event: %s', $event ), 'webhook' );
        if ( is_array( $data ) ) {
            Logger::log_debug( 'Webhook payload keys: ' . implode( ', ', array_keys( $data ) ), 'webhook' );
        }

        switch ($event) {
            case 'release':
                if (isset($data['action']) && $data['action'] === 'published') {
                    Logger::log('New release published. Clearing package transients to force update check.', 'info', 'webhook');
                    $this->package_finder->clear_cache();
                    delete_transient('wp2_merged_packages_data');
                    // Force a new update check.
                    wp_update_themes();
                    wp_update_plugins();
                }
                break;
            case 'ping':
                Logger::log('Ping event received from GitHub. Webhook is active.', 'info', 'webhook');
                break;
            case 'installation_repositories':
                Logger::log('Repository list updated. Triggering a new sync.', 'info', 'webhook');
                // Asynchronously re-sync repositories for this app.
                \WP2\Update\Core\Tasks\Scheduler::schedule_sync_for_app($app_post_id);
                break;
            case 'installation':
                // NEW: Handle installation event to save the ID.
                if (isset($data['action']) && $data['action'] === 'created') {
                    // Ensure the payload contains the necessary data before proceeding
                    if (isset($data['installation']['id'], $data['installation']['app_id'])) {
                        Logger::log('New installation created. Saving the Installation ID.', 'info', 'webhook');
                        $models_init = new \WP2\Update\Admin\Models\Init();
                        $models_init->handle_github_installation_event($data);
                    } else {
                        Logger::log('Installation event payload is missing required fields.', 'error', 'webhook');
                    }
                }
                break;
            default:
                Logger::log("Webhook event '{$event}' not handled. Skipping.", 'info', 'webhook');
                break;
        }
    }

    /**
	 * Checks if the given IP address belongs to GitHub's trusted IP ranges.
	 *
	 * @param string $ip The IP address to validate.
	 * @return bool True if the IP is trusted, false otherwise.
	 */
	private function is_github_ip(string $ip): bool {
		$cache_key = 'github_ip_ranges';
		$github_ips = get_transient($cache_key);

		if ($github_ips === false) {
			$response = wp_remote_get('https://api.github.com/meta');

			if (is_wp_error($response)) {
				return false;
			}

			$data = json_decode(wp_remote_retrieve_body($response), true);

			if (!isset($data['hooks'])) {
				return false;
			}

			$github_ips = $data['hooks'];
			set_transient($cache_key, $github_ips, DAY_IN_SECONDS);
		}

		foreach ($github_ips as $cidr) {
			if ($this->ip_in_cidr($ip, $cidr)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if an IP address is within a given CIDR range.
	 *
	 * @param string $ip The IP address to check.
	 * @param string $cidr The CIDR range.
	 * @return bool True if the IP is within the range, false otherwise.
	 */
	private function ip_in_cidr(string $ip, string $cidr): bool {
		if (strpos($cidr, '/') === false) {
			return $ip === $cidr;
		}

		list($subnet, $mask) = explode('/', $cidr);

		$ip_packed = @inet_pton($ip);
		$subnet_packed = @inet_pton($subnet);

		if ($ip_packed === false || $subnet_packed === false) {
			// Invalid IP format
			return false;
		}

		$size = strlen($ip_packed);

		$mask_bytes = str_repeat(chr(255), floor($mask / 8));
		$remainder = $mask % 8;
		if ($remainder > 0) {
			$mask_bytes .= chr((0xFF << (8 - $remainder)) & 0xFF);
		}
		$mask_bytes = str_pad($mask_bytes, $size, chr(0), STR_PAD_RIGHT);

		return ($ip_packed & $mask_bytes) === ($subnet_packed & $mask_bytes);
	}
}
