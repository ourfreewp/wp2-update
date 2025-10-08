<?php

namespace WP2\Update\Utils;

if (!defined('ABSPATH')) {
    exit;
}

use WP2\Update\Init;
use WP2\Update\Core\API\Service;


/**
 * Small collection of helpers used across the trimmed plugin.
 */
final class SharedUtils
{
    private \WP2\Update\Core\API\GitHubApp\Init $githubApp;
    private Service $githubService;

    public function __construct(\WP2\Update\Core\API\GitHubApp\Init $githubApp, Service $githubService)
    {
        $this->githubApp = $githubApp;
        $this->githubService = $githubService;
    }

    /**
     * Normalize version strings so they can be compared reliably.
     */
    public function normalize_version(?string $version): string
    {
        $normalized = ltrim((string) $version, 'v');
        return $normalized !== '' ? $normalized : '0.0.0';
    }

    /**
     * Extract an owner/repo pair from an Update URI value.
     */
    public function normalize_repo(?string $uri): ?string
    {
        if (!$uri) {
            return null;
        }

        $uri = trim($uri);

        if ($uri === '') {
            return null;
        }

        if (preg_match('~(?:https?://github\.com/)?([^/]+/[^/]+)~', $uri, $matches)) {
            return rtrim($matches[1], '/');
        }

        if (strpos($uri, '/') !== false && !preg_match('~^https?://~', $uri)) {
            return trim($uri, '/');
        }

        return null;
    }

    /**
     * Locate the most appropriate download URL from a release payload.
     */
    public function get_zip_url_from_release(array $release): ?string
    {
        $assets = $release['assets'] ?? [];

        foreach ($assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $type = $asset['content_type'] ?? '';
            if (in_array($type, ['application/zip', 'application/x-zip-compressed'], true) && !empty($asset['browser_download_url'])) {
                return (string) $asset['browser_download_url'];
            }
        }

        if (!empty($release['zipball_url'])) {
            return (string) $release['zipball_url'];
        }

        return null;
    }

    /**
     * Retrieves the GitHub Webhook Secret securely.
     * @return string The webhook secret.
     */
    public function get_webhook_secret(): string
    {
        // Retrieve the secret from the database or environment
        $secret = get_option('wp2_update_webhook_secret');

        if (!$secret) {
            // Fallback to environment variable if not set in the database
            $secret = getenv('WP2_UPDATE_WEBHOOK_SECRET');
        }

        if (!$secret) {
            throw new \RuntimeException('Webhook secret is not configured.');
        }

        return $secret;
    }
}
