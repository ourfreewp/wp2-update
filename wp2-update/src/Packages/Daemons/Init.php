<?php
// src/Packages/Daemons/Init.php

namespace WP2\Update\Packages\Daemons;

use WP2\Update\Core\Package;
use WP2\Update\Packages\Daemons\Discovery;
use WP2\Update\Packages\Daemons\Admin as DaemonAdmin;
use Composer\Semver\Comparator;
use Composer\Semver\Semver;

class Init implements Package {
    private $admin;
    private $managed = [];

    public function detect(): array {
        $this->managed = Discovery::detect();
        return $this->managed;
    }

    public function hook_updates(): void {
    add_filter('pre_set_site_transient_wp2_update_daemons', [$this, 'update_daemons_transient']);
    // Developer extensibility: allow filtering managed daemons
    $this->managed = apply_filters('wp2_update_daemons_managed', $this->managed);
    }

    public function get_admin(): ?DaemonAdmin {
        if (!empty($this->managed)) {
            $this->admin = new DaemonAdmin();
            return $this->admin;
        }
        return null;
    }

    public function update_daemons_transient($transient) {
        if (empty($transient->checked) || empty($this->managed)) return $transient;
        foreach ($this->managed as $file => $data) {
            if (empty($transient->checked[$file])) continue;
            $releases = \WP2\Update\Helpers\Github::get_releases($data['repo']);
            if (empty($releases)) continue;
            usort($releases, fn($a, $b) => strtotime($b->published_at) <=> strtotime($a->published_at));
            $latest = $releases[0];
            $current = $transient->checked[$file];
            $latest_version = preg_replace('/^v/i', '', $latest->tag_name);
            // Use composer/semver for advanced comparison
            if (!Semver::satisfies($current, '>=' . $latest_version)) {
                $transient->response[$file] = [
                    'slug' => $file,
                    'new_version' => $latest->tag_name,
                    'url' => $latest->html_url ?? '',
                    'package' => \WP2\Update\Helpers\Github::find_zip_asset($latest),
                ];
            }
        }
        return $transient;
    }

    private function get_daemon_version($daemon_file) {
        $daemons = get_mu_plugins();
        return isset($daemons[$daemon_file]['Version']) ? $daemons[$daemon_file]['Version'] : '0.0.0';
    }

    public function add_download_auth($reply, $package, $upgrader) {
        if (strpos($package, 'api.github.com') !== false && $this->github_pat) {
            add_filter('http_request_args', function ($args, $url) use ($package) {
                if ($url === $package) {
                    $args['headers']['Authorization'] = 'token ' . $this->github_pat;
                    $args['headers']['Accept'] = 'application/octet-stream';
                }
                return $args;
            }, 10, 2);
        }
        return $reply;
    }
}
