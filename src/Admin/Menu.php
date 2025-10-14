<?php

namespace WP2\Update\Admin;

/**
 * Handles the registration of admin menus for the WP2 Update plugin.
 */
final class Menu {
    private Screens $screens;

    /**
     * Constructor for the Menu class.
     *
     * @param Screens $screens The service responsible for rendering the admin pages.
     */
    public function __construct(Screens $screens) {
        $this->screens = $screens;
    }

    /**
     * Registers the main admin menu page for the plugin.
     * This method hooks into WordPress's 'admin_menu' action.
     */
    public function register_menu(): void {
        error_log('WP2 Update: register_menu method called.');
        error_log('register_menu method is running.');

        add_menu_page(
            __('WP2 Updates', \WP2\Update\Config::TEXT_DOMAIN), // Page Title
            __('WP2 Updates', \WP2\Update\Config::TEXT_DOMAIN), // Menu Title
            'manage_options',                // Capability
            \WP2\Update\Config::TEXT_DOMAIN,                    // Menu Slug
            [$this->screens, 'render'],      // Callback function to render the page
            'dashicons-cloud',               // Icon URL
            81                               // Position
        );

        // This submenu is hidden but necessary to handle the GitHub App setup callback.
        add_submenu_page(
            null, // Parent slug (null to hide)
            __('GitHub Callback', \WP2\Update\Config::TEXT_DOMAIN),
            __('GitHub Callback', \WP2\Update\Config::TEXT_DOMAIN),
            'manage_options',
            'wp2-update-github-callback',
            [$this->screens, 'render_github_callback']
        );
    }
}
