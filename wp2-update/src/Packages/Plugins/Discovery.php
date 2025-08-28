<?php

namespace WP2\Update\Packages\Plugins;

class Discovery {
    public static function detect(): array {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $plugins = get_plugins();
        $managed = [];
        foreach ($plugins as $file => $data) {
            $update_uri = trim($data['UpdateURI'] ?? '');
            if (preg_match('/^[A-Za-z0-9._-]+\/[A-Za-z0-9._-]+$/', $update_uri)) {
                $managed[$file] = [
                    'file' => $file,
                    'repo' => $update_uri,
                    'name' => $data['Name'],
                ];
            }
        }
        return $managed;
    }
}