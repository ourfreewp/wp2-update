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
            'permission_callback' => '__return_true',
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
}