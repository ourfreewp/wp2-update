<?php
declare(strict_types=1);

namespace WP2\Update\Utils;

defined('ABSPATH') || exit;

/**
 * A collection of static helper methods for formatting and normalizing data.
 */
final class Formatting
{
    /**
     * Normalizes a version string by removing any leading 'v'.
     *
     * @param string|null $version The version string to normalize.
     * @return string The normalized version string.
     */
    public static function normalize_version(?string $version): string
    {
        return ltrim((string) $version, 'v');
    }

    /**
     * Normalizes a repository URI or slug into the 'owner/repo' format.
     *
     * @param string|null $uri The repository URI, slug, or name.
     * @return string|null The normalized 'owner/repo' string, or null if parsing fails.
     */
    public static function normalize_repo(?string $uri): ?string
    {
        $uri = trim((string) $uri);
        if (empty($uri)) {
            return null;
        }

        // Pattern to extract 'owner/repo' from various GitHub URL formats.
        if (preg_match('~github\.com/([^/]+/[^/]+?)(?:\.git|/)?$~', $uri, $matches)) {
            return $matches[1];
        }

        // Assume it's already in 'owner/repo' format if it contains a slash.
        if (strpos($uri, '/') !== false) {
            return $uri;
        }

        return null;
    }
}
