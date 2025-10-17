<?php

namespace WP2\Update\Admin;

use WP2\Update\Config;
use WP2\Update\Utils\Logger;

/**
 * Handles the registration of admin menus for the WP2 Update plugin.
 */
final class Pages {


    public function __construct() {
        // Constructor is now empty as no dependencies are required.
    }


    /**
     * Renders the main admin page, which acts as the root for the SPA.
     */
    public function render(): void {
        echo '<div id="wp2-update-app" class="wp2-wrap"></div>';
    }

    /**
     * Registers the main admin menu page for the plugin.
     * Consolidates logic for single-site and multisite environments.
     */
    public function register_menu(): void {
        $capability = is_multisite() ? 'manage_network_options' : 'manage_options';

        add_menu_page(
            __('WP2 Update', Config::TEXT_DOMAIN), // Page Title
            __('WP2 Update', Config::TEXT_DOMAIN), // Menu Title
            $capability,                          // Capability
            Config::TEXT_DOMAIN,                  // Menu Slug
            [$this, 'render'],           // Callback function to render the page
            'dashicons-cloud',                    // Icon URL
            81                                    // Position
        );

        // Hidden submenu for GitHub App setup callback.
        add_submenu_page(
            null, // Parent slug (null to hide)
            __('GitHub Callback', Config::TEXT_DOMAIN),
            __('GitHub Callback', Config::TEXT_DOMAIN),
            $capability, // Use the same capability as the main menu
            'wp2-update-github-callback',
            [$this, 'render_github_callback']
        );
    }
}
