<?php
// src/Packages/Plugins/Init.php

namespace WP2\Update\Packages\Plugins;

use WP2\Update\Core\Package;
use WP2\Update\Packages\Plugins\Discovery;
use WP2\Update\Packages\Plugins\Admin as PluginAdmin;
use WP2\Update\Utils\Log;
use Composer\Semver\Semver;

class Init implements Package {
    private $admin;
    private $managed = [];

    public function detect(): array {
        $this->managed = Discovery::detect();
        return $this->managed;
    }

    public function hook_updates(): void {
    add_filter('pre_set_site_transient_update_plugins', [$this, 'update_plugins_transient']);
    add_filter('upgrader_pre_download', [$this, 'add_download_auth'], 10, 3);
    // Developer extensibility: allow filtering managed plugins
    $this->managed = apply_filters('wp2_update_plugins_managed', $this->managed);
    }

    public function get_admin(): ?PluginAdmin {
        if (!empty($this->managed)) {
            $this->admin = new PluginAdmin();
            return $this->admin;
        }
        return null;
    }
    public function update_plugins_transient($transient) {
        if (empty($transient->checked) || empty($this->managed)) return $transient;
        foreach ($this->managed as $file => $data) {
            if (empty($transient->checked[$file])) continue;
            $releases = \WP2\Update\Helpers\Github::get_releases($data['repo']);
            if (empty($releases)) continue;
            usort($releases, fn($a, $b) => strtotime($b->published_at) <=> strtotime($a->published_at));
            $latest = $releases[0];
            $installed = $this->get_plugin_version($file);
            $latest_version = preg_replace('/^v/i', '', $latest->tag_name);
            // Use composer/semver for advanced comparison
            if (!Semver::satisfies($installed, '>=' . $latest_version)) {
                $transient->response[$file] = (object) [
                    'slug' => $data['slug'],
                    'new_version' => $latest->tag_name,
                    'url' => $latest->html_url ?? '',
                    'package' => \WP2\Update\Helpers\Github::find_zip_asset($latest),
                ];
            }
        }
        return $transient;
    }

    private function get_plugin_version($plugin_file) {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = get_plugins();
        return isset($plugins[$plugin_file]['Version']) ? $plugins[$plugin_file]['Version'] : '0.0.0';
    }

    private function find_zip_asset($release) {
        if (isset($release->assets) && is_array($release->assets)) {
            foreach ($release->assets as $asset) {
                if (isset($asset->content_type) && 'application/zip' === $asset->content_type) {
                    return $asset->url ?? $asset->browser_download_url ?? '';
                }
            }
        }
        return '';
    }

    public function add_download_auth($reply, $package, $upgrader) {
        if (strpos($package, 'api.github.com/repos') !== false) {
            add_filter('http_request_args', function ($args, $url) use ($package) {
                if ($url === $package) {
                    $token = \WP2\Update\Helpers\GithubAppAuth::get_token();
                    if ($token) {
                        $args['headers']['Authorization'] = 'Bearer ' . $token;
                        $args['headers']['Accept'] = 'application/octet-stream';
                    }
                }
                return $args;
            }, 10, 2);
        }
        return $reply;
    }
}
