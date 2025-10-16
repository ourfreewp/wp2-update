<?php

namespace WP2\Update\Admin;

use WP2\Update\Config;

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

        // Directly register the menu for single-site environments
        if (!is_multisite()) {
            add_menu_page(
                __('WP2 Update', Config::TEXT_DOMAIN), // Page Title
                __('WP2 Update', Config::TEXT_DOMAIN), // Menu Title
                'manage_options',                // Capability
                Config::TEXT_DOMAIN,               // Menu Slug
                [$this->screens, 'render'],      // Callback function to render the page
                'dashicons-cloud',               // Icon URL
                81                               // Position
            );
        }

        // Directly register the menu for multisite environments
        if (is_multisite()) {
            add_menu_page(
                __('WP2 Update', Config::TEXT_DOMAIN), // Page Title
                __('WP2 Update', Config::TEXT_DOMAIN), // Menu Title
                'manage_network_options',        // Capability
                Config::TEXT_DOMAIN,                    // Menu Slug
                [$this->screens, 'render'],      // Callback function to render the page
                'dashicons-cloud',               // Icon URL
                81                               // Position
            );
        }

        // This submenu is hidden but necessary to handle the GitHub App setup callback.
        add_submenu_page(
            null, // Parent slug (null to hide)
            __('GitHub Callback', Config::TEXT_DOMAIN),
            __('GitHub Callback', Config::TEXT_DOMAIN),
            'manage_options',
            'wp2-update-github-callback',
            [$this->screens, 'render_github_callback']
        );
    }
}
