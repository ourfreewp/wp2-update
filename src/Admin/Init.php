<?php

namespace WP2\Update\Admin;

use WP2\Update\Admin\Menu\Manager as MenuManager;
use WP2\Update\Admin\Assets\Manager as AssetManager;
use WP2\Update\Core\API\ConnectionService;
use WP2\Update\Core\Updates\PackageService;
use WP2\Update\Utils\Logger;

/**
 * Initializes all admin-facing functionality, including menus and assets.
 */
final class Init {
    private ConnectionService $connectionService;
    private PackageService $packageService;

    /**
     * Constructor for the Init class.
     *
     * @param ConnectionService $connectionService Service for managing GitHub connections.
     * @param PackageService $packageService Service for managing package updates.
     */
    public function __construct(ConnectionService $connectionService, PackageService $packageService) {
        $this->connectionService = $connectionService;
        $this->packageService = $packageService;
    }

    /**
     * Registers all necessary hooks for the admin area.
     *
     * This method initializes the menu manager and asset manager, and hooks them
     * into the appropriate WordPress actions.
     */
    public function register_hooks(): void {
        try {
            // Register the menu pages.
            $menuManager = new MenuManager($this->connectionService, $this->packageService);
            add_action('admin_menu', [$menuManager, 'register_menu']);

            // Register the asset enqueueing hooks.
            AssetManager::register_hooks();
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                Logger::log('ERROR', 'Admin Initialization Error: ' . $e->getMessage());
            }

            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>' . esc_html__('An error occurred during admin initialization. Please check the logs for details.', 'wp2-update') . '</p></div>';
            });
        }
    }
}

