<?php

namespace WP2\Update\Admin\Menu;

use WP2\Update\Core\API\ConnectionService;
use WP2\Update\Core\Updates\PackageService;
use WP2\Update\Admin\Screens\Manager as GitHubScreensManager;

/**
 * Class Manager
 * Handles the registration of admin menus for the WP2 Update plugin.
 */
final class Manager {
    private ConnectionService $connectionService;
    private PackageService $packageService;

    /**
     * Constructor for the Manager class.
     *
     * @param ConnectionService $connectionService Service for managing GitHub connections.
     * @param PackageService $packageService Service for managing package updates.
     */
    public function __construct(ConnectionService $connectionService, PackageService $packageService) {
        $this->connectionService = $connectionService;
        $this->packageService = $packageService;
    }

    /**
     * Registers the admin menu and submenu for the plugin.
     *
     * This method hooks into WordPress to add the main menu and a hidden submenu
     * for handling GitHub callbacks.
     */
    public function register_menu(): void {
        do_action('wp2_update_before_register_menu');

        add_menu_page(
            esc_html__('WP2 Updates', 'wp2-update'),
            esc_html__('WP2 Updates', 'wp2-update'),
            'manage_options',
            'wp2-update',
            [GitHubScreensManager::class, 'render'],
            'dashicons-cloud'
        );

        add_submenu_page(
            null,
            esc_html__('GitHub Callback', 'wp2-update'),
            esc_html__('GitHub Callback', 'wp2-update'),
            'manage_options',
            'wp2-update-github-callback',
            [GitHubScreensManager::class, 'render_github_callback']
        );

        do_action('wp2_update_after_register_menu');
    }
}
