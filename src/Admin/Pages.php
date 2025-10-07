<?php
namespace WP2\Update\Admin;

use WP2\Update\Core\Connection\Init as Connection;
use WP2\Update\Core\API\GitHubApp\Init as GitHubApp;
use WP2\Update\Utils\SharedUtils;
use WP2\Update\Admin\Pages\OverviewPage;
use WP2\Update\Admin\Pages\PackageEventsPage;
use WP2\Update\Admin\Pages\PackagesPage;
use WP2\Update\Admin\Pages\SystemHealthPage;
use WP2\Update\Admin\Pages\PackageHistoryPage;
use WP2\Update\Admin\Pages\PackageStatusPage;


/**
 * Renders the admin page framework and delegates tab content rendering.
 */
class Pages {
    private $connection;
    private $github_app;
    private $utils;
    private $container;

    private $packages_page;

    /**
     * Constructor.
     */
    public function __construct( Connection $connection, GitHubApp $github_app, SharedUtils $utils, $container ) {
        $this->connection = $connection;
        $this->github_app = $github_app;
        $this->utils      = $utils;
        $this->container  = $container;

        $package_finder = $this->container->resolve('PackageFinder');
        $history_tab = new PackageHistoryPage( $this->connection, $this->utils );
        $status_tab = new PackageStatusPage( $this->connection, $this->github_app, $this->utils );
        $log_tab = new PackageEventsPage();

        $this->packages_page = new PackagesPage( 
            $this->connection, 
            $this->utils, 
            $this->github_app, 
            $package_finder, 
            $history_tab, 
            $status_tab, 
            $log_tab 
        );
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
     * Renders the overview page.
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
     * Renders the Bulk Actions page.
     */
    public function render_bulk_actions_page() {
        echo '<h1>Bulk Actions Page</h1>';
        echo '<p>This page is under construction.</p>';
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

    /**
     * Renders the Logs page.
     */
    public function render_logs_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('All Logged Events', 'wp2-update') . '</h1>';
        echo '<p>' . __('A log of all system events, from API calls to update checks.', 'wp2-update') . '</p>';

        // Render the logs table dynamically
        $logs_table = new \WP2\Update\Admin\Tables\LogsTable();
        $logs_table->prepare_items();
        $logs_table->display();

        echo '</div>';
    }

    /**
     * Renders the GitHub App Settings page.
     */
    public function render_github_app_settings_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'GitHub App Settings', 'wp2-update' ) . '</h1>';
        echo '<form method="post" action="options.php">';

        // Output security fields for the registered setting
        settings_fields( 'wp2_update_github_app_settings' );

        // Output setting sections and their fields
        do_settings_sections( 'wp2-update-settings' );

        // Submit button
        submit_button( __( 'Save Settings', 'wp2-update' ) );

        echo '</form>';
        echo '</div>';
    }
}
