<?php
namespace WP2\Update\Packages\Themes;

use WP2\Update\Core\Package;
use WP2\Update\Packages\Themes\Discovery;
use WP2\Update\Packages\Themes\Admin as ThemeAdmin;
use Composer\Semver\Semver;

class Init implements Package {
    private $admin;
    private $managed = [];

    public function detect(): array {
        $this->managed = Discovery::detect();
        return $this->managed;
    }

    public function hook_updates(): void {
    add_filter('pre_set_site_transient_update_themes', [$this, 'update_themes_transient'], 10, 1);
    // Developer extensibility: allow filtering managed themes
    $this->managed = apply_filters('wp2_update_themes_managed', $this->managed);
    }

    public function get_admin(): ?ThemeAdmin {
        if (!empty($this->managed)) {
            $this->admin = new ThemeAdmin($this->managed);
            return $this->admin;
        }
        return null;
    }

    public function update_themes_transient($transient) {
        if (empty($transient->checked) || empty($this->managed)) return $transient;
        foreach ($this->managed as $slug => $data) {
            if (empty($transient->checked[$slug])) continue;
            $releases = \WP2\Update\Helpers\Github::get_releases($data['repo']);
            if (empty($releases)) continue;
            usort($releases, fn($a, $b) => strtotime($b->published_at) <=> strtotime($a->published_at));
            $latest = $releases[0];
            $current = $transient->checked[$slug];
            $latest_version = preg_replace('/^v/i', '', $latest->tag_name);
            // Use composer/semver for advanced comparison
            if (!Semver::satisfies($current, '>=' . $latest_version)) {
                $transient->response[$slug] = [
                    'theme' => $slug,
                    'new_version' => $latest->tag_name,
                    'url' => $latest->html_url ?? '',
                    'package' => \WP2\Update\Helpers\Github::find_zip_asset($latest),
                ];
            }
        }
        return $transient;
    }
}
