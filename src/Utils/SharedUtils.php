<?php

namespace WP2\Update\Utils;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Small collection of helpers used across the trimmed plugin.
 */
final class SharedUtils
{
    /**
     * Constructor updated to remove GitHubApp dependency.
     */
    public function __construct()
    {
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
     * Normalize a repository URI or string.
     *
     * @param string|null $uri The repository URI or string.
     * @return string|null The normalized repository string or null if invalid.
     */
    public static function normalize_repo(?string $uri): ?string
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
     * Logs an error message.
     *
     * @param string $message The error message to log.
     */
    public function log_error(string $message): void
    {
        error_log('[WP2 Update] ' . $message);
    }
}
