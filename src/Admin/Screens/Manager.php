<?php

namespace WP2\Update\Admin\Pages;

use WP2\Update\Core\Updates\PackageFinder;

final class Manager {
    public static function render(): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('WP2 Updates', 'wp2-update'); ?></h1>
            <div id="wp2-update-root"></div>
        </div>
        <?php
    }

    public static function render_github_callback(): void {
        wp_enqueue_script(
            'wp2-update-github-callback',
            WP2_UPDATE_PLUGIN_URL . 'assets/scripts/github-callback.js',
            [],
            null,
            true
        );
        wp_localize_script('wp2-update-github-callback', 'wp2UpdateData', [
            'nonce' => wp_create_nonce('wp_rest'),
        ]);

        ?>
        <div id="wp2-update-github-callback"></div>
        <?php
    }
}