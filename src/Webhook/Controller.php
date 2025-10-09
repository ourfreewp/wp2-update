<?php

namespace WP2\Update\Webhook;

use WP2\Update\Core\API\CredentialService;
use WP2\Update\Utils\Logger;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles incoming webhooks from GitHub to trigger plugin and theme update checks.
 */
final class Controller {
    private CredentialService $credentialService;

    public function __construct(CredentialService $credentialService) {
        $this->credentialService = $credentialService;
    }

    /**
     * Registers the REST API route for the webhook endpoint.
     */
    public function register_route(): void {
        register_rest_route('wp2-update/v1', '/webhook', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle'],
            'permission_callback' => '__return_true', // Endpoint is public; security is handled by signature validation.
        ]);
    }

    /**
     * Handles the incoming webhook request from GitHub.
     *
     * Validates the request signature and, if valid, clears the update transients
     * to force WordPress to check for new plugin/theme versions.
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @return WP_REST_Response
     */
    public function handle(WP_REST_Request $request): WP_REST_Response {
        $payload   = $request->get_body();
        $signature = $request->get_header('X-Hub-Signature-256');

        if (empty($payload) || empty($signature)) {
            Logger::log('SECURITY', 'Webhook validation failed: Missing payload or signature.');
            return new WP_REST_Response(['message' => 'Missing payload or signature.'], 400);
        }

        $secret = $this->credentialService->get_decrypted_webhook_secret();
        if (empty($secret)) {
            Logger::log('SECURITY', 'Webhook validation failed: Webhook secret not configured in WordPress.');
            return new WP_REST_Response(['message' => 'Webhook secret not configured.'], 401);
        }

        $expected_hash = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        if (!hash_equals($expected_hash, $signature)) {
            Logger::log('SECURITY', 'Webhook validation failed: Invalid signature.');
            return new WP_REST_Response(['message' => 'Signature validation failed.'], 401);
        }

        $data = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::log('SECURITY', 'Webhook validation failed: Invalid JSON payload.');
            return new WP_REST_Response(['message' => 'Invalid JSON.'], 400);
        }

        $event = (string) $request->get_header('X-GitHub-Event');

        // Only act on the 'published' action of a 'release' event.
        if ($event === 'release' && ($data['action'] ?? '') === 'published') {
            Logger::log('INFO', 'Valid release webhook received. Clearing update transients.');
            delete_site_transient('update_plugins');
            delete_site_transient('update_themes');
            do_action('wp2_update_release_published', $data);
        }

        return new WP_REST_Response(['message' => 'Webhook processed successfully.'], 200);
    }
}
