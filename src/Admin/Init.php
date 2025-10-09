<?php

namespace WP2\Update\Admin;

use WP2\Update\Admin\Menu\Manager as MenuManager;
use WP2\Update\Admin\Assets\Manager as AssetManager;
use WP2\Update\Core\API\ConnectionService;
use WP2\Update\Core\Updates\PackageService;

final class Init {
    private ConnectionService $connectionService;
    private PackageService $packageService;

    public function __construct(ConnectionService $connectionService, PackageService $packageService) {
        $this->connectionService = $connectionService;
        $this->packageService = $packageService;
    }

    public function register_hooks(): void {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [AssetManager::class, 'enqueue_admin_assets']);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('admin_notices', [$this, 'render_debug_panel']);
        }
    }

    public function register_admin_menu(): void {
        $menuManager = new MenuManager($this->connectionService, $this->packageService);
        $menuManager->register_menu();
    }

    public function render_debug_panel(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $user = wp_get_current_user();
        $debug = [
            'user'       => ['id' => $user->ID, 'login' => $user->user_login],
            'admin_url'  => admin_url(),
            'ajax_url'   => admin_url('admin-ajax.php'),
            'localized_data' => isset($data) ? $data : 'wp2UpdateData is not defined.',
            'i18n_function' => function_exists('__') ? 'Available' : 'Not defined. Ensure wp-i18n is loaded.',
            'available_actions' => [
                'start-connection',
                'disconnect',
                'sync-packages',
                'update-package'
            ],
        ];
        ?>
        <div class="notice notice-info is-dismissible">
            <h2><?php echo esc_html__('WP2 Update Debug Panel', 'wp2-update'); ?></h2>
            <pre><?php echo esc_html(print_r($debug, true)); ?></pre>
        </div>
        <?php
    }
}