<?php
namespace WP2\Update\Admin;

use WP2\Update\Core\Connection\Init as Connection;
use WP2\Update\Core\API\GitHubApp\Init as GitHubApp;
use WP2\Update\Utils\SharedUtils;
use WP2\Update\Admin\Pages\ChangelogPage;
use WP2\Update\Admin\Pages\OverviewPage;
use WP2\Update\Admin\Pages\PackageEventsPage;
use WP2\Update\Admin\Pages\PackagesPage;
use WP2\Update\Admin\Pages\SystemHealthPage;
use WP2\Update\Admin\Pages\BackupManagementPage;
use WP2\Update\Admin\Pages\PackageHistoryPage;
use WP2\Update\Admin\Pages\PackageStatusPage;


/**
 * Renders the admin page framework and delegates tab content rendering.
 */
class Pages {
    private $connection;
    private $github_app;
    private $utils;

    private $packages_page;

    /**
     * Constructor.
     */
    public function __construct( Connection $connection, GitHubApp $github_app, SharedUtils $utils ) {
        $this->connection = $connection;
        $this->github_app = $github_app;
        $this->utils      = $utils;

        $this->packages_page = new PackagesPage( $this->connection, $this->utils, $this->github_app );
    }

    /**
     * Enqueues admin assets only on our plugin's pages.
     */
    public function enqueue_assets( $hook_suffix ) {
        if ( ! is_string( $hook_suffix ) || strpos((string)$hook_suffix, 'wp2-update' ) === false ) {
            return;
        }
        wp_enqueue_style( 'wp2-update-admin', WP2_UPDATE_PLUGIN_URL . 'assets/styles/admin-main.css', [], '0.1.6' );
        wp_enqueue_script( 'tabby-js', 'https://cdn.jsdelivr.net/npm/tabbyjs@12.0.3/dist/js/tabby.polyfills.min.js', [], '12.0.3', true );
        wp_enqueue_script( 'wp2-update-admin', WP2_UPDATE_PLUGIN_URL . 'assets/scripts/admin-main.js', [ 'tabby-js' ], '0.1.6', true );

        wp_localize_script( 'wp2-update-admin', 'wpApiSettings', [
            'nonce' => wp_create_nonce( 'wp_rest' )
        ] );

        wp_enqueue_style( 'bootstrap-icons', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css', [], '1.13.1' );

        // Add filter to set type="module" for admin-main.js
        add_filter( 'script_loader_tag', function ( $tag, $handle, $src ) {
            if ( 'wp2-update-admin' === $handle ) {
                $tag = '<script type="module" src="' . esc_url( $src ) . '"></script>';
            }
            return $tag;
        }, 10, 3 );
    }

    /**
     * Renders the dedicated packages page.
     */
    public function render_packages_page() {
        $this->packages_page->render();
    }

    /**
     * Renders the dedicated settings page.
     */
    public function render_settings_page() {
        echo '<h1>Settings Page</h1>';
        echo '<p>This page is under construction.</p>';
    }

    /**
     * * Renders the overview page.
     */
    public function render_overview_page() {
        $view = new OverviewPage($this->connection, $this->github_app, $this->utils);
        $view->render();
    }

    /**
     * Renders the System Health page.
     */
    public function render_system_health_page() {
        $view = new SystemHealthPage($this->connection, $this->github_app, $this->utils);
        $view->render();
    }

    /**
     * Renders the Changelog page.
     */
    public function render_changelog_page() {
        $view = new ChangelogPage($this->connection, $this->utils);
        $view->render();
    }

    /**
     * Renders the Bulk Actions page.
     */
    public function render_bulk_actions_page() {
        echo '<h1>Bulk Actions Page</h1>';
        echo '<p>This page is under construction.</p>';
    }
    
    /**
     * Renders the Events page
     */
    public function render_events_page() {
        $events_page = new PackageEventsPage();
        $events_page->render_as_view();
    }

    /**
     * Renders the Backup Management page.
     */
    public function render_backup_management_page() {
        $view = new BackupManagementPage($this->utils);
        $view->render();
    }

    /**
     * Renders the Package Events page.
     */
    public function render_package_events_page() {
        $view = new PackageEventsPage();
        $view->render_as_view();
    }

    /**
     * Renders the Package History page.
     */
    public function render_package_history_page() {
        $view = new PackageHistoryPage($this->connection, $this->utils);
        $view->render(null, null);
    }

    /**
     * Renders the Package Status page.
     */
    public function render_package_status_page() {
        $view = new PackageStatusPage($this->connection, $this->github_app, $this->utils);
        $view->render(null, null);
    }
}
