<?php
namespace WP2\Update\Admin;

use WP2\Update\Core\Connection\Init as Connection;
use WP2\Update\Core\API\GitHubApp\Init as GitHubApp;
use WP2\Update\Core\Updates\PluginUpdater;
use WP2\Update\Core\Updates\ThemeUpdater;
use WP2\Update\Utils\SharedUtils;
use WP2\Update\Admin\Models\Init as Models;
use WP2\Update\Admin\Actions\Controller as Actions;
use WP2\Update\Core\Tasks\Scheduler as TaskScheduler;

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

    /** @var mixed The DI container instance. */
    private $container;

    /**
     * Constructor.
     */
    public function __construct( Connection $connection, GitHubApp $github_app, ThemeUpdater $theme_updater, PluginUpdater $plugin_updater, SharedUtils $utils, TaskScheduler $task_scheduler, $container ) {
        $this->container = $container;

        // Instantiate page renderers
        $this->pages_handler = new Pages( $connection, $github_app, $utils, $this->container );

        // Instantiate action handler
        $this->actions = new Actions( $connection, $github_app, $theme_updater, $plugin_updater, $utils, $task_scheduler );

        // Instantiate models handler
        $this->models = new Models();

        // Register the admin menu pages
        add_action( 'admin_menu', [ $this, 'register_admin_pages' ] );
    }

    /**
     * Registers all necessary WordPress admin hooks.
     */
    public function register_hooks() {
        // Register standard actions.
        add_action( 'admin_init', [ $this->actions, 'handle_admin_actions' ] );
        add_action( 'admin_post_wp2_theme_install', [ $this->actions, 'handle_theme_install_action' ] );
        add_action( 'admin_post_wp2_plugin_install', [ $this->actions, 'handle_plugin_install_action' ] );
        add_action( 'admin_post_wp2_test_connection', [ $this->actions, 'handle_test_connection_action' ] );
        add_action( 'admin_post_wp2_bulk_action', [ $this->actions, 'handle_bulk_actions' ] );
        add_action( 'admin_post_wp2_run_scheduler', [ $this->actions, 'handle_run_scheduler_action' ] );

        // Register model hooks.
        $this->models->register();

        // Register filters for submenu persistence (NOT inside an admin_menu action).
        add_filter( 'parent_file', [ $this, 'filter_parent_file' ] );
        add_filter( 'submenu_file', [ $this, 'filter_submenu_file' ] );
    }

    /**
     * Registers the admin menu and submenu pages.
     */
    public function register_admin_pages() {
        add_menu_page(
            'WP2 Updates',
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

        // Add dynamic post type submenu pages
        $post_types = [
            'wp2_repository' => __( 'Repositories', 'wp2-update' ),
            'wp2_github_app' => __( 'GitHub Apps', 'wp2-update' ),
        ];

        foreach ( $post_types as $post_type => $label ) {
            add_submenu_page(
                'wp2-update-overview',
                $label,
                $label,
                'manage_options',
                "edit.php?post_type={$post_type}"
            );
        }
    }

    /**
     * Filter for parent file to ensure submenu persistence.
     */
    public function filter_parent_file( $parent_file ) {
        global $pagenow;
        $post_types = [ 'wp2_repository', 'wp2_github_app' ];

        if ( $pagenow === 'edit.php' && isset( $_GET['post_type'] ) && in_array( $_GET['post_type'], $post_types, true ) ) {
            return 'wp2-update-overview';
        }

        return $parent_file;
    }

    /**
     * Filter for submenu file to ensure submenu persistence.
     */
    public function filter_submenu_file( $submenu_file ) {
        global $pagenow;
        $post_types = [
            'wp2_repository' => 'edit.php?post_type=wp2_repository',
            'wp2_github_app' => 'edit.php?post_type=wp2_github_app',
        ];

        if ( $pagenow === 'edit.php' && isset( $_GET['post_type'] ) && isset( $post_types[ $_GET['post_type'] ] ) ) {
            return $post_types[ $_GET['post_type'] ];
        }

        return $submenu_file;
    }
}
