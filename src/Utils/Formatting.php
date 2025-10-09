<?php

namespace WP2\Update\Utils;

/**
 * A collection of static helper methods for formatting and normalizing data.
 */
final class Formatting
{
    /**
     * Normalize a version string by removing a 'v' prefix.
     *
     * @param string|null $version The version string to normalize.
     * @return string The normalized version string.
     */
    public static function normalize_version(?string $version): string
    {
        $normalized = ltrim((string) $version, 'v');
        return $normalized !== '' ? $normalized : '0.0.0';
    }

    /**
     * Normalize a repository URI or slug into the 'owner/repo' format.
     *
     * @param string|null $uri The repository URI or slug.
     * @return string|null The normalized 'owner/repo' string or null if invalid.
     */
    public static function normalize_repo(?string $uri): ?string
    {
        if (empty(trim((string) $uri))) {
            return null;
        }

        // Handles full GitHub URLs
        if (preg_match('~(?:https?://github\.com/)?([^/]+/[^/]+)~', $uri, $matches)) {
            return rtrim($matches[1], '/');
        }

        // Handles 'owner/repo' slugs
        if (strpos($uri, '/') !== false && !preg_match('~^https?://~', $uri)) {
            return trim($uri, '/');
        }

        return null;
    }
}