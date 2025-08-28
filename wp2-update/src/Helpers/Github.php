<?php
namespace WP2\Update\Helpers;

use WP2\Update\Utils\Log;

class Github {
    public static function get_releases(string $repo): ?array {
        return self::make_request("https://api.github.com/repos/{$repo}/releases");
    }

    public static function get_release_by_tag(string $repo, string $tag): ?object {
        return self::make_request("https://api.github.com/repos/{$repo}/releases/tags/{$tag}");
    }

    public static function find_zip_asset(?object $release): string {
        if (isset($release->assets) && is_array($release->assets)) {
            foreach ($release->assets as $asset) {
                if (($asset->content_type ?? '') === 'application/zip') {
                    return $asset->browser_download_url ?? $asset->url ?? '';
                }
            }
        }
        return '';
    }

    private static function make_request(string $url, array $args = []) {
        $token = \WP2\Update\Helpers\GithubAppAuth::get_token();
        if (!$token) {
            Log::add("Aborting GitHub API request: No auth token for {$url}", 'error', 'github-api');
            return null;
        }
        $defaults = [
            'timeout' => 15,
            'headers' => [
                'User-Agent'    => 'WP2Update/1.0',
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/vnd.github.v3+json',
            ]
        ];
        $response = wp_remote_get($url, array_merge_recursive($defaults, $args));
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            Log::add("GitHub API request to {$url} failed", 'error', 'github-api');
            return null;
        }
        return json_decode(wp_remote_retrieve_body($response));
    }
}
