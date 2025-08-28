<?php

namespace WP2\Update\Packages\Themes;

class Discovery {
    public static function detect(): array {
        $themes = wp_get_themes();
        $managed = [];
        foreach ($themes as $slug => $theme) {
            $update_uri = trim((string) $theme->get('UpdateURI') ?: $theme->get('Update URI'));
            if (preg_match('/^[A-Za-z0-9._-]+\/[A-Za-z0-9._-]+$/', $update_uri)) {
                $managed[$slug] = [
                    'slug' => $slug,
                    'repo' => $update_uri,
                    'name' => $theme->get('Name'),
                ];
            }
        }
        return $managed;
    }
}