<?php
// src/Packages/Daemons/Discovery.php

namespace WP2\Update\Packages\Daemons;

class Discovery {
    /**
     * Detects managed daemons by scanning the MU-plugins for an 'UpdateURI' header.
     *
     * @return array
     */
    public static function detect(): array {
        if (!function_exists('get_mu_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $mu_plugins = get_mu_plugins();
        $managed = [];
        foreach ($mu_plugins as $file => $data) {
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