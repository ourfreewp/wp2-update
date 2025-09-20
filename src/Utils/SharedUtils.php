<?php
namespace WP2\Update\Utils;

use WP2\Update\Core\GitHubApp\Init as GitHubApp;

use const HOUR_IN_SECONDS;

class SharedUtils {
    private $github_app;

    public function __construct(GitHubApp $github_app) {
        $this->github_app = $github_app;
    }

    public function get_all_releases(string $app_slug, string $repo, int $count = 10): array {
        $cache_key = 'wp2_releases_' . md5($repo);
        if (false !== ($cached = get_transient($cache_key))) {
            return is_array($cached) ? $cached : [];
        }

        $res = $this->github_app->gh($app_slug, 'GET', "/repos/{$repo}/releases", ['per_page' => $count]);

        if (!$res['ok']) {
            Logger::log('Error fetching releases for repository: ' . $repo, 'error', 'github', ['error' => $res['error'] ?? 'Unknown error']);
            return [];
        }

        $releases = is_array($res['data']) ? array_filter($res['data'], fn($r) => is_array($r) && empty($r['draft']) && empty($r['prerelease'])) : [];
        set_transient($cache_key, $releases, HOUR_IN_SECONDS);
        return $releases;
    }

    public function get_updates_count(): int {
        $themes = get_site_transient('update_themes');
        $plugins = get_site_transient('update_plugins');

        $updates_count = 0;

        if (!empty($themes->response)) {
            $updates_count += count($themes->response);
        }

        if (!empty($plugins->response)) {
            $updates_count += count($plugins->response);
        }

        return $updates_count;
    }

    public static function normalize_version(?string $version): string {
        if ($version === null) {
            return '0.0.0'; // Default version if null is provided
        }

        return ltrim($version, 'v');
    }

    public function get_zip_url_from_release(array $release): ?string {
        foreach (($release['assets'] ?? []) as $asset) {
            if (in_array($asset['content_type'], ['application/zip', 'application/x-zip-compressed'], true)) {
                return $asset['url'];
            }
        }
        return $release['zipball_url'] ?? null;
    }

    public static function normalize_repo(?string $uri): ?string {
        if (empty($uri)) {
            return null;
        }

        // Handle full github.com URLs
        if (preg_match('/^https?:\/\/github\.com\/([^\/]+\/[^\/]+)\/?$/', $uri, $matches)) {
            return rtrim($matches[1], '/');
        }

        // Handle simple owner/repo slugs
        if (preg_match('/^([^\/]+\/[^\/]+)$/', $uri, $matches)) {
            return rtrim($matches[1], '/');
        }

        // Return null for invalid URIs
        return null;
    }

    /**
     * Gets the last time WordPress checked for updates.
     *
     * @return string A human-readable time difference.
     */
    public function get_last_checked_time(): string {
        $themes_transient = get_site_transient('update_themes');
        $last_checked = $themes_transient->last_checked ?? 0;

        if (empty($last_checked)) {
            return __('Never', 'wp2-update');
        }
        return sprintf(__('%s ago', 'wp2-update'), human_time_diff($last_checked));
    }

    /**
     * Finds the main plugin file in a given directory.
     *
     * @param string $directory The directory to search.
     * @return string|null The path to the main plugin file, or null if not found.
     */
    public function get_plugin_file(string $directory): ?string {
        $files = scandir($directory);
        if (!$files) {
            return null;
        }

        foreach ($files as $file) {
            if (preg_match('/^[a-zA-Z0-9-_]+\.php$/', $file)) {
                $file_path = trailingslashit($directory) . $file;
                $contents = file_get_contents($file_path);
                if (strpos($contents, 'Plugin Name:') !== false) {
                    return $file_path;
                }
            }
        }

        return null;
    }
}
