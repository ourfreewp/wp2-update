<?php

namespace WP2\Update\Admin\Assets;

use WP2\Update\Utils\Logger;

final class Manager {
    public static function enqueue_admin_assets(): void {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== 'toplevel_page_wp2-update') {
            return;
        }

        $manifest_path = trailingslashit(WP2_UPDATE_PLUGIN_DIR) . 'dist/.vite/manifest.json';
        if (!file_exists($manifest_path)) {
            Logger::log('ERROR', 'Vite manifest not found: ' . basename($manifest_path)); // Updated logging
            return;
        }

        $manifest = json_decode((string) file_get_contents($manifest_path), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::log('ERROR', 'Vite manifest decode failed: ' . json_last_error_msg()); // Updated logging
            return;
        }

        if (!empty($manifest['assets/scripts/admin-main.js']['file'])) {
            $js = $manifest['assets/scripts/admin-main.js']['file'];
            $version = $manifest['assets/scripts/admin-main.js']['hash'] ?? null; // Extract version from manifest

            // Dynamically parse dependencies from the Vite manifest
            $dependencies = $manifest['assets/scripts/admin-main.js']['imports'] ?? [];

            wp_enqueue_script(
                'wp2-update-admin-main',
                WP2_UPDATE_PLUGIN_URL . 'dist/' . $js,
                $dependencies,
                $version, // Use dynamic version for cache-busting
                true
            );
            wp_localize_script(
                'wp2-update-admin-main',
                'wpApiSettings',
                [
                    'root'  => esc_url_raw(rest_url()),
                    'nonce' => wp_create_nonce('wp_rest'),
                ]
            );
        }

        if (!empty($manifest['assets/styles/admin-main.scss']['file'])) {
            $css = $manifest['assets/styles/admin-main.scss']['file'];
            wp_enqueue_style('wp2-update-admin-main', WP2_UPDATE_PLUGIN_URL . 'dist/' . $css, [], null);
        }

        wp_localize_script(
            'wp2-update-admin-main',
            'wp2UpdateL10n',
            [
                'noReleasesFound' => esc_html__('No releases found.', 'wp2-update'),
                'syncFailed'      => esc_html__('Sync failed. Please try again.', 'wp2-update'),
                'rollbackPrompt'  => esc_html__('Enter the version to rollback to:', 'wp2-update'),
                'rollbackSuccess' => esc_html__('Rollback completed successfully.', 'wp2-update'),
                'rollbackFailed'  => esc_html__('Rollback failed. See console for details.', 'wp2-update'),
            ]
        );
    }
}