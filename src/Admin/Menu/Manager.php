<?php

namespace WP2\Update\Admin\Menu;

use WP2\Update\Core\API\ConnectionService;
use WP2\Update\Core\Updates\PackageService;
use WP2\Update\Admin\Screens\Manager as GitHubScreensManager;

final class Manager {
    private ConnectionService $connectionService;
    private PackageService $packageService;

    public function __construct(ConnectionService $connectionService, PackageService $packageService) {
        $this->connectionService = $connectionService;
        $this->packageService = $packageService;
    }

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
