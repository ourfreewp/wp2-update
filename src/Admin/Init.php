<?php

namespace WP2\Update\Admin;

use WP2\Update\Admin\Menu\Manager as MenuManager;
use WP2\Update\Admin\Assets\Manager as AssetManager;
use WP2\Update\Admin\Debug\Manager as DebugManager;
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
            add_action('admin_notices', [DebugManager::class, 'render_debug_panel']);
        }
    }

    public function register_admin_menu(): void {
        $menuManager = new MenuManager($this->connectionService, $this->packageService);
        $menuManager->register_menu();
    }
}