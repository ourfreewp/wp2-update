<?php
namespace WP2\Update\Webhooks;

defined('ABSPATH') || exit;

use WP2\Update\Services\Github\ClientService;
use WP2\Update\Services\Github\ReleaseService;
use WP_REST_Request;
use WP_REST_Response;
use WP2\Update\Utils\Logger;
use WP2\Update\Utils\Encryption;

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
     * Validates the webhook signature against the specific app secret.
     *
     * @param string $payload The raw request body.
     * @param string $signature The X-Hub-Signature-256 header value.
     * @param string $appId The GitHub App ID.
     * @return bool True if the signature is valid, false otherwise.
     */
    private function validate_signature(string $payload, string $signature, string $appId): bool {
        $appData = new \WP2\Update\Data\AppData();
        $app = $appData->find($appId);

        if (!$app || empty($app->webhook_secret)) {
            return false;
        }

        $secret = $app->webhook_secret; // Use the secret directly without decrypting again
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        // Iterate over all stored webhook secrets if app_id is not trusted
        $allSecrets = $appData->get_all_webhook_secrets();
        $isValid = false;
        foreach ($allSecrets as $storedSecret) {
            $computedSignature = 'sha256=' . hash_hmac('sha256', $payload, $storedSecret);
            if (hash_equals($computedSignature, $signature)) {
                $isValid = true;
                break;
            }
        }

        return $isValid;
    }

    /**
     * Validates the structure of the webhook payload.
     *
     * @param array $payload The decoded webhook payload.
     * @return bool True if the payload is valid, false otherwise.
     */
    private function validate_payload(array $payload): bool {
        return isset($payload['repository']['full_name']) && isset($payload['action']);
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

                return $this->validate_signature($payload, $signature, $appId);
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

        // Validate the signature against the specific app secret
        $valid = $this->validate_signature($payload, $signature, $appId);

        if (!$valid) {
            Logger::error('Webhook signature validation failed.', ['event' => $event]);
            return new WP_REST_Response(['message' => 'Invalid signature.'], 403);
        }

        Logger::info('Webhook signature validated.', ['event' => $event]);

        // Decode and validate the payload
        $decodedPayload = json_decode($payload, true);
        if (!$this->validate_payload($decodedPayload)) {
            Logger::error('Invalid webhook payload structure.', ['payload' => $decodedPayload]);
            return new WP_REST_Response(['message' => 'Invalid payload structure.'], 400);
        }

        // Schedule an async action instead of processing synchronously
        $uniqueActionId = md5(json_encode(['event' => $event, 'payload' => $decodedPayload, 'app_id' => $appId]));
        if (!as_has_scheduled_action('wp2_update_handle_webhook', ['unique_id' => $uniqueActionId], 'wp2-update')) {
            as_schedule_single_action(
                time(),
                'wp2_update_handle_webhook',
                [
                    'event'     => $event,
                    'payload'   => array_merge($decodedPayload, ['__attempt' => 1]),
                    'app_id'    => $appId,
                    'unique_id' => $uniqueActionId,
                ],
                'wp2-update'
            );
        }

        return new WP_REST_Response(['message' => 'Webhook received.'], 200);
    }
}
