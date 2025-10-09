<?php

namespace WP2\Update\Webhook;

use WP2\Update\Core\API\CredentialService;
use WP2\Update\Utils\Logger;

final class Controller {
    private CredentialService $credentialService;

    public function __construct(CredentialService $credentialService) {
        $this->credentialService = $credentialService;
    }

    public function register_route(): void {
        register_rest_route('wp2-update/v1', '/webhook', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle'],
            'permission_callback' => function() {
                Logger::log('SECURITY', 'Public webhook endpoint accessed.');
                return true; // Keep the endpoint publicly accessible
            },
        ]);

        register_rest_route('wp2-update/v1', '/disconnect', [
            'methods'             => 'POST',
            'callback'            => [$this, 'disconnect'],
            'permission_callback' => '__return_true', // Publicly accessible
        ]);

        register_rest_route('wp2-update/v1', '/callback', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handle_callback'],
            'permission_callback' => '__return_true', // Publicly accessible
        ]);
    }

    public function handle(\WP_REST_Request $request): \WP_REST_Response {
        $payload   = $request->get_body();
        $signature = $request->get_header('X-Hub-Signature-256');

        if ($payload === '' || $signature === '') {
            Logger::log('SECURITY', 'Webhook validation failed: Missing payload or signature.');
            return new \WP_REST_Response(['message' => 'Missing payload or signature.'], 400);
        }

        $rawSecret = $this->credentialService->get_decrypted_webhook_secret();
        if ($rawSecret === '') {
            Logger::log('SECURITY', 'Webhook validation failed: Webhook secret not configured.');
            return new \WP_REST_Response(['message' => 'Webhook secret not configured.'], 401); // Updated response code
        }

        $hash = 'sha256=' . hash_hmac('sha256', $payload, $rawSecret);
        if (!hash_equals($hash, $signature)) {
            Logger::log('SECURITY', 'Webhook validation failed: Signature validation failed.');
            return new \WP_REST_Response(['message' => 'Signature validation failed.'], 401);
        }

        $data = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::log('SECURITY', 'Webhook validation failed: Invalid JSON payload.');
            return new \WP_REST_Response(['message' => 'Invalid JSON.'], 400);
        }

        $event = (string) $request->get_header('X-GitHub-Event');

        if ($event === 'release' && ($data['action'] ?? '') === 'published') {
            delete_site_transient('update_plugins');
            delete_site_transient('update_themes');
            do_action('wp2_update_release_published', $data);
        }

        return new \WP_REST_Response(['ok' => true], 200);
    }

    public function disconnect(): \WP_REST_Response {
        $success = $this->credentialService->clear_credentials();

        if (!$success) {
            Logger::log('ERROR', 'Failed to clear credentials during disconnect.');
            return new \WP_REST_Response(['message' => 'Failed to disconnect.'], 500);
        }

        Logger::log('INFO', 'Successfully disconnected GitHub App.');
        return new \WP_REST_Response(['message' => 'Disconnected successfully.'], 200);
    }

    public function handle_callback(\WP_REST_Request $request): \WP_REST_Response {
        $code = $request->get_param('code');
        $state = $request->get_param('state');

        if (empty($code) || empty($state)) {
            Logger::log('ERROR', 'Callback validation failed: Missing code or state.');
            return new \WP_REST_Response(['message' => 'Missing code or state.'], 400);
        }

        // Exchange the code for an access token
        $token = $this->credentialService->exchange_code_for_token($code);

        if (!$token) {
            Logger::log('ERROR', 'Failed to exchange code for token.');
            return new \WP_REST_Response(['message' => 'Failed to authenticate.'], 500);
        }

        Logger::log('INFO', 'GitHub App callback handled successfully.');
        return new \WP_REST_Response(['message' => 'Authenticated successfully.'], 200);
    }
}