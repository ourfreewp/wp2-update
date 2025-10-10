<?php

namespace WP2\Update\Admin\Screens;

/**
 * Handles rendering the admin screen for the WP2 Update plugin.
 * Renders the minimal "app shell" for the JavaScript SPA.
 */
final class Manager {

    public static function render(): void {
        ?>
        <div id="wp2-update-app" class="wp2-container">
            <h1 class="wp2-main-title"><?php esc_html_e('GitHub Package Updater', 'wp2-update'); ?></h1>

            <!-- Dashboard container. JavaScript renders all views within this element. -->
            <div id="wp2-dashboard-root" class="wp2-dashboard-root"></div>

            <!-- Manual Credentials container -->
            <div id="wp2-manual-credentials" class="wp2-container" hidden></div>

            <?php self::render_modal(); ?>
        </div>
        <?php
    }

    private static function render_modal(): void {
        ?>
        <div id="wp2-disconnect-modal" class="wp2-modal" hidden>
            <div class="wp2-modal-content">
                <h3 class="wp2-modal-heading"><?php esc_html_e('Confirm Action', 'wp2-update'); ?></h3>
                <p class="wp2-modal-message"></p>
                <div class="wp2-modal-actions">
                    <button class="wp2-button wp2-button-secondary" data-wp2-action="cancel-disconnect">
                        <?php esc_html_e('Cancel', 'wp2-update'); ?>
                    </button>
                    <button class="wp2-button wp2-button-danger" data-wp2-action="confirm-disconnect">
                        <?php esc_html_e('Confirm', 'wp2-update'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    public static function render_github_callback(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wp2-update'));
        }
        ?>
        <div id="wp2-update-github-callback" class="wp2-wrap">
            <p class="wp2-p"><?php esc_html_e('Completing GitHub App setup…', 'wp2-update'); ?></p>
        </div>
        <?php
    }
}
