<?php

namespace WP2\Update\Webhooks;

use WP2\Update\Services\Github\ConnectionService;
use WP2\Update\Services\Github\ClientService;
use WP2\Update\Services\Github\ReleaseService;
use WP2\Update\Utils\Logger;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles incoming webhooks from GitHub to trigger plugin and theme update checks.
 */
final class WebhookController {
    private ConnectionService $connectionService;
    private ClientService $clientService;
    private ReleaseService $releaseService;

    public function __construct(ConnectionService $connectionService, ClientService $clientService, ReleaseService $releaseService) {
        $this->connectionService = $connectionService;
        $this->clientService = $clientService;
        $this->releaseService = $releaseService;
    }

    /**
     * Registers the REST API route for the webhook endpoint.
     */
    public function register_route(): void {
        register_rest_route('wp2-update/v1', '/webhook', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle'],
            'permission_callback' => function (WP_REST_Request $request) {
                $signature = $request->get_header('X-Hub-Signature-256');
                $payload = $request->get_body();
                $secrets = $this->connectionService->get_all_webhook_secrets();

                if (empty($signature) || empty($payload)) {
                    Logger::log('SECURITY', 'Webhook rejected: Missing signature or payload.');
                    return false;
                }

                foreach ($secrets as $secret) {
                    $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
                    if (hash_equals($expectedSignature, $signature)) {
                        return true;
                    }
                }

                Logger::log('SECURITY', 'Webhook rejected: Invalid signature.');
                return false;
            },
        ]);
    }

    /**
     * Handles the incoming webhook request from GitHub asynchronously.
     */
    public function handle(WP_REST_Request $request): WP_REST_Response {
        $payload = $request->get_body();
        $signature = $request->get_header('X-Hub-Signature-256');
        $event = (string) $request->get_header('X-GitHub-Event');

        if (empty($payload) || empty($signature) || empty($event)) {
            Logger::log('SECURITY', 'Webhook rejected: Missing payload, signature, or event header.');
            return new WP_REST_Response(['message' => 'Invalid request.'], 400);
        }

        // Ensure secrets are mapped to their respective app IDs
        $secrets = $this->connectionService->get_all_webhook_secrets();
        if (empty($secrets)) {
            Logger::log('SECURITY', 'Webhook received but no secrets are configured.');
            return new WP_REST_Response(['message' => 'Webhook not configured.'], 401);
        }

        // Validate the signature against all configured apps
        $valid_app_id = null;
        foreach ($secrets as $app_id => $secret) {
            $expected_hash = 'sha256=' . hash_hmac('sha256', $payload, $secret);
            if (hash_equals($expected_hash, $signature)) {
                $valid_app_id = $app_id;
                break;
            }
        }

        if (!$valid_app_id) {
            Logger::log('SECURITY', 'Webhook rejected: Invalid signature.');
            return new WP_REST_Response(['message' => 'Invalid signature.'], 403);
        }

        // Schedule an async action instead of processing synchronously
        as_schedule_single_action(
            time(),
            'wp2_update_handle_webhook',
            [
                'event'   => $event,
                'payload' => json_decode($payload, true),
                'app_id'  => $valid_app_id,
            ],
            'wp2-update'
        );

        return new WP_REST_Response(['message' => 'Webhook received.'], 200);
    }

    /**
     * Processes the webhook event after validation.
     * @param string $event The GitHub event name (e.g., 'release').
     * @param array $data The payload data.
     * @param string $app_id The ID of the app that received the webhook.
     */
    private function process_event(string $event, array $data, string $app_id): void {
        // Handle installation events to automatically save the installation ID.
        if ($event === 'installation' && isset($data['installation']['id'])) {
            $this->connectionService->update_installation_id($app_id, (int)$data['installation']['id']);
            Logger::log('INFO', "Processed installation event for app {$app_id}.");
            return;
        }

        // Handle release events to trigger update checks.
        if ($event === 'release' && ($data['action'] ?? '') === 'published') {
            $repository = $data['repository']['full_name'] ?? null;
            if ($repository) {
                Logger::log('INFO', "Release published webhook received for repository {$repository}. Pre-fetching and caching new release data.");

                [$owner, $repo] = explode('/', $repository);
                $latestRelease = $this->releaseService->get_latest_release($repository, 'stable');

                if ($latestRelease) {
                    $cacheKey = sprintf(\WP2\Update\Config::TRANSIENT_LATEST_RELEASE, $owner, $repo);
                    \WP2\Update\Utils\Cache::set($cacheKey, $latestRelease, 5 * MINUTE_IN_SECONDS);
                    Logger::log('INFO', "Cached latest release for repository {$repository}.");
                } else {
                    Logger::log('WARNING', "Failed to fetch latest release for repository {$repository}.");
                }
            } else {
                Logger::log('WARNING', "Release published webhook received but repository information is missing.");
            }

            do_action('wp2_update_release_published', $data, $app_id);
            return;
        }

        Logger::log('DEBUG', "Webhook event '{$event}' received but not acted upon for app {$app_id}.");
    }
}
