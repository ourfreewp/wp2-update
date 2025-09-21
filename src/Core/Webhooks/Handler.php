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
                    Logger::log('New installation created. Saving the Installation ID.', 'info', 'webhook');
                    $models_init = new \WP2\Update\Admin\Models\Init();
                    $models_init->handle_github_installation_event($data);
                }
                break;
            default:
                Logger::log("Webhook event '{$event}' not handled. Skipping.", 'info', 'webhook');
                break;
        }
    }
}
