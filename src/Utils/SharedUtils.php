<?php
namespace WP2\Update\Core\Utils;

use WP2\Update\Core\GitHubApp\Init as GitHubApp;
use WP2\Update\Core\Utils\Logger; // Corrected namespace for Logger

class SharedUtils {
    private $github_app;

    public function __construct(GitHubApp $github_app) {
        $this->github_app = $github_app;
    }

    public function get_all_releases(string $app_slug, string $repo, int $count = 10): array {
        $cache_key = 'wp2_releases_' . md5($repo);
        if (false !== ($cached = get_transient($cache_key))) {
            return $cached;
        }

        $res = $this->github_app->gh($app_slug, 'GET', "/repos/{$repo}/releases", ['per_page' => $count]);

        if (!$res['ok']) {
            Logger::log('Error fetching releases for repository: ' . $repo . ' - ' . ($res['error'] ?? 'Unknown error'), 'error', 'github');
            return [];
        }

        $releases = array_filter($res['data'], fn($r) => is_array($r) && empty($r['draft']) && empty($r['prerelease']));
        set_transient($cache_key, $releases, HOUR_IN_SECONDS);
        return $releases;
    }

    public function repository_exists(string $app_slug, string $repo): bool {
        $response = $this->github_app->gh($app_slug, 'GET', "/repos/{$repo}");

        if (!$response['ok']) {
            return false;
        }

        return !empty($response['data']);
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

    public static function normalize_repo(string $uri): ?string {
        // Normalize the repository URI to a standard format.
        if (empty($uri)) {
            return null;
        }

        // Example normalization logic
        if (preg_match('/github\.com\/([^\/]+\/[^\/]+)/', $uri, $matches)) {
            return $matches[1];
        }

        return null;
    }
}