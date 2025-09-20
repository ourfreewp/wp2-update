<?php
namespace WP2\Update\Admin;

use WP2\Update\Core\Connection\Init as Connection;
use WP2\Update\Core\GitHubApp\Init as GitHubApp;
use WP2\Update\Core\Updates\PluginUpdater;
use WP2\Update\Core\Updates\ThemeUpdater;
use WP2\Update\Core\Utils\Init as SharedUtils;
use WP2\Update\Admin\Models\Init as Models;
use WP2\Update\Admin\Actions\Controller as Actions;
use WP2\Update\Core\API\Service as GitHubService;

/**
 * Main orchestrator for the admin interface.
 * Initializes and registers all admin-related components and hooks.
 */
class Init {
    /** @var Pages The settings page handler. */
    private Pages $pages_handler;

    /** @var Actions The admin action handler. */
    private Actions $actions;

    /** @var Models The models handler. */
    private Models $models;

    /**
     * Constructor.
     */
    public function __construct( Connection $connection, GitHubApp $github_app, ThemeUpdater $theme_updater, PluginUpdater $plugin_updater, SharedUtils $utils, GitHubService $github_service ) {
        // Instantiate page renderers
        $this->pages_handler = new Pages( $connection, $github_app, $utils, $github_service );

        // Instantiate action handler
        $this->actions = new Actions( $connection, $github_app, $theme_updater, $plugin_updater, $utils );

        // Instantiate models handler
        $this->models = new Models();
    }

    /**
     * Registers all necessary WordPress admin hooks.
     */
    public function register_hooks() {
        add_action( 'admin_menu', [ $this, 'register_admin_pages' ] );
        add_action( 'admin_init', [ $this->actions, 'handle_admin_actions' ] );
        add_action( 'admin_enqueue_scripts', [ $this->pages_handler, 'enqueue_assets' ] );
        
        add_action( 'admin_post_wp2_theme_install', [ $this->actions, 'handle_theme_install_action' ] );
        add_action( 'admin_post_wp2_plugin_install', [ $this->actions, 'handle_plugin_install_action' ] );
        add_action( 'admin_post_wp2_test_connection', [ $this->actions, 'handle_test_connection_action' ] );
        add_action( 'admin_post_wp2_bulk_action', [ $this->actions, 'handle_bulk_actions' ] );

        // Register model hooks
        $this->models->register();
    }

    /**
     * Registers the admin menu and submenu pages.
     */
    public function register_admin_pages() {
        add_menu_page(
            __( 'WP2 Updates', 'wp2-update' ),
            'WP2 Updates',
            'manage_options',
            'wp2-update-overview',
            [ $this->pages_handler, 'render_overview_page' ],
            'dashicons-cloud',
            2
        );

        add_submenu_page(
            'wp2-update-overview',
            __( 'Packages', 'wp2-update' ),
            __( 'Packages', 'wp2-update' ),
            'manage_options',
            'wp2-update-packages',
            [ $this->pages_handler, 'render_packages_page' ]
        );

        add_submenu_page(
            'wp2-update-overview',
            __( 'System Health', 'wp2-update' ),
            __( 'System Health', 'wp2-update' ),
            'manage_options',
            'wp2-update-system-health',
            [ $this->pages_handler, 'render_system_health_page' ]
        );

        add_submenu_page(
            'wp2-update-overview',
            __( 'Changelog', 'wp2-update' ),
            __( 'Changelog', 'wp2-update' ),
            'manage_options',
            'wp2-update-changelog',
            [ $this->pages_handler, 'render_changelog_page' ]
        );

        add_submenu_page(
            'wp2-update-overview',
            __( 'Bulk Actions', 'wp2-update' ),
            __( 'Bulk Actions', 'wp2-update' ),
            'manage_options',
            'wp2-update-bulk-actions',
            [ $this->pages_handler, 'render_bulk_actions_page' ]
        );
        
        add_submenu_page(
            'wp2-update-overview',
            __( 'Settings', 'wp2-update' ),
            __( 'Settings', 'wp2-update' ),
            'manage_options',
            'wp2-update-settings',
            [ $this->pages_handler, 'render_settings_page' ]
        );

        add_submenu_page(
            'wp2-update-overview',
            __( 'Events', 'wp2-update' ),
            __( 'Events', 'wp2-update' ),
            'manage_options',
            'wp2-update-events',
            [ $this->pages_handler, 'render_events_page' ]
        );
    }
}
