<?php

namespace WP2\Update\Admin\Screens;

final class Manager {
    public static function render(): void {
        self::render_header('WP2 Updates');
        echo '<div id="wp2-update-root"></div>';
        echo '<div id="wp2-update-app"></div>'; // Ensure this exists
        echo '<div class="wp2-wrap">';
        echo '  <section id="pre-connection" class="workflow-step">';
        echo '    <h3>' . esc_html__( 'Connect GitHub App', 'wp2-update' ) . '</h3>';
        echo '    <p>' . esc_html__( 'Start the connection flow to authorize your GitHub App.', 'wp2-update' ) . '</p>';
        echo '    <button class="button button-primary" data-action="start-connection">' . esc_html__( 'Connect', 'wp2-update' ) . '</button>';
        echo '  </section>';
        echo '  <section id="manage-packages" class="workflow-step" hidden>'; 
        echo '    <h3>' . esc_html__( 'Packages', 'wp2-update' ) . '</h3>';
        echo '    <table id="wp2-package-table" class="widefat striped"><tbody></tbody></table>';
        echo '  </section>';
        echo '</div>';
        echo '<div id="disconnect-modal" class="wp2-modal" hidden>…</div>';
        self::render_footer();
    }

    public static function render_github_callback(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp2-update'));
        }

        if (!check_admin_referer('wp_rest')) {
            wp_die(__('Invalid request. Please try again.', 'wp2-update'));
        }

        self::enqueue_github_callback_script();
        echo '<div id="wp2-update-github-callback"></div>';
    }

    private static function render_header(string $title): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__($title, 'wp2-update') . '</h1>';
    }

    private static function render_footer(): void {
        echo '</div>';
    }

    private static function enqueue_github_callback_script(): void {
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
    }
}