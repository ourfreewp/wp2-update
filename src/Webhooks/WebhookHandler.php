<?php

namespace WP2\Update\Webhooks;

use WP2\Update\Config;

/**
 * Handles the processing of webhook payloads in the background.
 */
class WebhookHandler {
    /**
     * Processes the webhook payload.
     *
     * @param string $event The GitHub event name.
     * @param array $payload The webhook payload data.
     * @param string $app_id The ID of the app that received the webhook.
     */
    public static function handle(string $event, array $payload, string $app_id): void {
        $container = new \WP2\Update\Container(); // Resolve services via DI container

        // Handle installation events to automatically save the installation ID.
        if ($event === 'installation' && isset($payload['installation']['id'])) {
            $connectionService = $container->get(\WP2\Update\Services\Github\AppService::class);
            $connectionService->update_installation_id($app_id, (int)$payload['installation']['id']);
            return;
        }

        // Handle release events to trigger update checks.
        if ($event === 'release' && ($payload['action'] ?? '') === 'published') {
            $repository = $payload['repository']['full_name'] ?? null;
            if ($repository) {
                $releaseService = $container->get(\WP2\Update\Services\Github\ReleaseService::class);
                [$owner, $repo] = explode('/', $repository);
                $latestRelease = $releaseService->get_latest_release($repository, 'stable');

                if ($latestRelease) {
                    $cacheKey = sprintf(Config::TRANSIENT_LATEST_RELEASE, $owner, $repo);
                    \WP2\Update\Utils\Cache::set($cacheKey, $latestRelease, 5 * MINUTE_IN_SECONDS);
                }
            }

            do_action('wp2_update_release_published', $payload, $app_id);
            return;
        }
    }
}

// Register the action with Action Scheduler
add_action('wp2_update_handle_webhook', [WebhookHandler::class, 'handle'], 10, 3);