<?php

namespace WP2\Update\Webhooks;

use WP2\Update\Services\Github\ClientService;
use WP2\Update\Services\Github\ReleaseService;
use WP_REST_Request;
use WP_REST_Response;
use WP2\Update\Utils\Logger;

/**
 * Class WebhookController
 *
 * Handles incoming webhooks from GitHub to trigger plugin and theme update checks.
 */
final class WebhookController {
    /**
     * @var ClientService Handles interactions with the GitHub client.
     */
    private ClientService $clientService;

    /**
     * @var ReleaseService Handles operations related to GitHub releases.
     */
    private ReleaseService $releaseService;

    /**
     * Constructor for WebhookController.
     *
     * @param ClientService $clientService Handles interactions with the GitHub client.
     * @param ReleaseService $releaseService Handles operations related to GitHub releases.
     */
    public function __construct(ClientService $clientService, ReleaseService $releaseService) {
        $this->clientService = $clientService;
        $this->releaseService = $releaseService;
    }

    /**
     * Retrieves the webhook secret for the given app ID.
     *
     * @param string $appId The GitHub App ID.
     * @return string|null The webhook secret, or null if not found.
     */
    private function get_webhook_secret(string $appId): ?string {
        $appData = new \WP2\Update\Data\AppData();
        $app = $appData->resolve_app_id($appId);
        return $app['webhook_secret'] ?? null;
    }

    /**
     * Validates the webhook signature against all known secrets.
     *
     * @param string $payload The raw request body.
     * @param string $signature The X-Hub-Signature-256 header value.
     * @return bool True if the signature is valid, false otherwise.
     */
    private function validate_signature(string $payload, string $signature): bool {
        $appData = new \WP2\Update\Data\AppData();
        $apps = $appData->get_all_apps();

        foreach ($apps as $app) {
            $secret = $app['webhook_secret'] ?? null;

            if (empty($secret)) {
                continue;
            }

            $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

            if (hash_equals($expectedSignature, $signature)) {
                return true;
            }
        }

        return false;
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
                $appId = $request->get_param('app_id');

                return $this->validate_signature($payload, $signature);
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
        $appId = $request->get_param('app_id');

        Logger::info('Incoming webhook received.', ['event' => $event, 'signature' => $signature]);
        Logger::debug('Webhook payload.', ['payload' => $payload]);

        if (empty($payload) || empty($signature) || empty($event)) {
            return new WP_REST_Response(['message' => 'Invalid request.'], 400);
        }

        // Validate the signature against the default secret
        $valid = $this->validate_signature($payload, $signature, $appId);

        if (!$valid) {
            Logger::error('Webhook signature validation failed.', ['event' => $event]);
            return new WP_REST_Response(['message' => 'Invalid signature.'], 403);
        }

        Logger::info('Webhook signature validated.', ['event' => $event]);

        // Schedule an async action instead of processing synchronously
        as_schedule_single_action(
            time(),
            'wp2_update_handle_webhook',
            [
                'event'   => $event,
                'payload' => json_decode($payload, true),
            ],
            'wp2-update'
        );

        return new WP_REST_Response(['message' => 'Webhook received.'], 200);
    }
}
