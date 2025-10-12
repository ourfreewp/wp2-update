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

    /**
     * Constructor for the Controller class.
     *
     * @param CredentialService $credentialService Service for managing GitHub credentials.
     */
    public function __construct(CredentialService $credentialService) {
        $this->credentialService = $credentialService;
    }

    /**
     * Registers the REST API route for the webhook endpoint.
     *
     * This route listens for POST requests to the `/webhook` endpoint.
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
     * @return WP_REST_Response Response indicating the result of the webhook handling.
     */
    public function handle(WP_REST_Request $request): WP_REST_Response {
        $payload   = $request->get_body();
        $signature = $request->get_header('X-Hub-Signature-256');

        if (empty($payload) || empty($signature)) {
            Logger::log('SECURITY', 'Webhook validation failed: Missing payload or signature.');
            return new WP_REST_Response(['message' => 'Missing payload or signature.'], 400);
        }

        $secretMap = $this->credentialService->get_all_webhook_secrets();
        if (empty($secretMap)) {
            Logger::log('SECURITY', 'Webhook validation failed: Webhook secret not configured in WordPress.');
            return new WP_REST_Response(['message' => 'Webhook secret not configured.'], 401);
        }

        $matchedApp = null;
        foreach ($secretMap as $appId => $secret) {
            $expected_hash = 'sha256=' . hash_hmac('sha256', $payload, $secret);
            if (hash_equals($expected_hash, $signature)) {
                $matchedApp = [
                    'id' => $appId,
                    'secret' => $secret,
                ];
                break;
            }
        }

        if (null === $matchedApp) {
            Logger::log('SECURITY', 'Webhook validation failed: Invalid signature.');
            return new WP_REST_Response(['message' => 'Signature validation failed.'], 401);
        }

        $data = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::log('SECURITY', 'Webhook validation failed: Invalid JSON payload.');
            return new WP_REST_Response(['message' => 'Invalid JSON.'], 400);
        }

        $event = (string) $request->get_header('X-GitHub-Event');

        // Log the event type for debugging purposes
        Logger::log('INFO', 'Webhook event received: ' . $event);

        // Capture installation events to persist the installation ID once the app is installed.
        if ($event === 'installation' && !empty($data['installation']['id'])) {
            $installationId = (int) $data['installation']['id'];
            $this->credentialService->update_installation_id($matchedApp['id'], $installationId);
            Logger::log('INFO', 'Installation webhook received. Installation ID stored: ' . $installationId);

            return new WP_REST_Response(['message' => 'Installation recorded.'], 200);
        }

        if ($event === 'installation_repositories' && !empty($data['installation']['id'])) {
            $installationId = (int) $data['installation']['id'];
            $this->credentialService->update_installation_id($matchedApp['id'], $installationId);
            Logger::log('INFO', 'Installation repositories webhook received. Installation ID stored: ' . $installationId);
        }

        // Only act on the 'published' action of a 'release' event.
        if ($event === 'release' && ($data['action'] ?? '') === 'published') {
            Logger::log('INFO', 'Valid release webhook received. Clearing update transients.');

            // Fetch managed repositories for the matched app
            $managedRepositories = $this->credentialService->get_managed_repositories($matchedApp['id']);

            if (empty($managedRepositories)) {
                Logger::log('WARNING', 'No managed repositories found for app: ' . $matchedApp);
                return new WP_REST_Response(['message' => 'No managed repositories found.'], 200);
            }

            // Clear update transients only for managed repositories
            delete_site_transient('update_plugins');
            delete_site_transient('update_themes');

            Logger::log('INFO', 'Cleared update transients for managed repositories: ' . implode(', ', $managedRepositories));
            do_action('wp2_update_release_published', $data, $matchedApp);
        }

        // Log unexpected events for debugging purposes.
        Logger::log('WARNING', 'Unexpected webhook event received: ' . json_encode([
            'event' => $event,
            'payload' => $data,
        ]));

        return new WP_REST_Response(['message' => 'Event not handled.'], 200);
    }
}
