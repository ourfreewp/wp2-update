<?php
namespace WP2\Update\Admin\Actions;

use WP2\Update\Core\Connection\Init as Connection;
use WP2\Update\Core\API\GitHubApp\Init as GitHubApp;
use WP2\Update\Core\Updates\PluginUpdater;
use WP2\Update\Core\Updates\ThemeUpdater;
use WP2\Update\Core\Tasks\Scheduler;
use WP2\Update\Utils\Logger;
use WP2\Update\Utils\SharedUtils;

/**
 * Handles all admin-side actions, such as form submissions and GitHubApp callbacks.
 */
class Controller {
    private $connection;
    private $github_app;
    private $theme_updater;
    private $plugin_updater;
    private $utils;
    private Scheduler $scheduler;

    /**
     * Constructor.
     */
    public function __construct( Connection $connection, GitHubApp $github_app, ThemeUpdater $theme_updater, PluginUpdater $plugin_updater, SharedUtils $utils, Scheduler $scheduler ) {
        $this->connection     = $connection;
        $this->github_app     = $github_app;
        $this->theme_updater  = $theme_updater;
        $this->plugin_updater = $plugin_updater;
        $this->utils          = $utils;
        $this->scheduler      = $scheduler;
    }

    /**
     * Logs GitHub App-related actions for debugging.
     */
    private function log_github_app_action( string $message, array $data = [] ): void {
        if ( empty( $data ) ) {
            Logger::log_debug( $message, 'github-app' );
            return;
        }

        Logger::log_debug(
            [
                'message' => $message,
                'data'    => $data,
            ],
            'github-app'
        );
    }

    /**
     * Logs an error message with context.
     *
     * @param string $message The error message.
     * @param string $context The context of the error.
     */
    private function log_error(string $message, string $context): void {
        Logger::log($message, 'error', $context);
    }

    /**
     * Main action router for events on standard admin pages.
     */
    public function handle_admin_actions() {
        if ( ! current_user_can( 'manage_options' ) || empty( $_GET['page'] ) || strpos((string)$_GET['page'], 'wp2-update') === false ) {
            return;
        }

        // Handle connected=1 parameter
        if ( isset( $_GET['connected'] ) && '1' === $_GET['connected'] ) {
            $this->log_github_app_action('Connected parameter detected', ['connected' => $_GET['connected']]);
            set_transient( 'wp2_update_admin_notice', esc_html__( 'Successfully connected to GitHub!', 'wp2-update' ), 60 );
        }

        // Handle the force-check action
        if ( isset( $_GET['force-check'] ) && '1' === $_GET['force-check'] ) {
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'wp2-force-check' ) ) {
                wp_safe_redirect( esc_url( admin_url( 'admin.php?page=wp2-update-system-health&error=security-check-failed' ) ) );
                exit;
            }

            // Clear transients and force a check
            $this->connection->clear_package_cache();
            wp_update_themes();
            wp_update_plugins();
            delete_transient('wp2_merged_packages_data');

            // Redirect back with a success message
            wp_safe_redirect( esc_url( admin_url( 'admin.php?page=wp2-update-system-health&cache-cleared=1' ) ) );
            exit;
        }

        if ( isset( $_GET['update-check'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['update-check'] ) ) ) {
            // Trigger WordPress update checks only when explicitly requested.
            wp_update_themes();
            wp_update_plugins();

            // Add a success notice for the next page load.
            set_transient( 'wp2_update_admin_notice', esc_html__( 'Update check triggered successfully.', 'wp2-update' ), 60 );

            // Redirect back to the originating WP2 admin page without the update-check flag.
            $target_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'wp2-update-system-health';
            $redirect_url = admin_url( 'admin.php?page=' . $target_page );
            wp_safe_redirect( $redirect_url );
            exit;
        }
    }

    /**
     * Manually triggers repository sync and health checks without relying on wp-cron.
     */
    public function handle_run_scheduler_action() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to perform this action.', 'wp2-update' ) );
        }

        check_admin_referer( 'wp2_run_scheduler_action' );

        $redirect_url = add_query_arg( 'manual-sync', 'success', admin_url( 'admin.php?page=wp2-update-system-health' ) );

        try {
            Logger::log( 'Manual scheduler run triggered from System Health.', 'info', 'tasks' );

            Logger::log('Checking if handle_run_scheduler_action is triggered.', 'debug', 'manual-sync');
            Logger::log('Request data: ' . print_r($_REQUEST, true), 'debug', 'manual-sync');

            Logger::log('Starting manual sync process.', 'debug', 'manual-sync');
            Logger::log('Scheduler instance: ' . print_r($this->scheduler, true), 'debug', 'manual-sync');
            Logger::log('Connection instance: ' . print_r($this->connection, true), 'debug', 'manual-sync');

            Logger::log('Nonce validation started.', 'debug', 'manual-sync');
            if (!check_admin_referer('wp2_run_scheduler_action')) {
                Logger::log('Nonce validation failed.', 'error', 'manual-sync');
                wp_die(__('Nonce validation failed.', 'wp2-update'));
            }
            Logger::log('Nonce validation passed.', 'debug', 'manual-sync');

            $this->scheduler->run_sync_all_repos();

            $apps_query = new \WP_Query([
                'post_type'      => 'wp2_github_app',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]);

            if ( $apps_query->have_posts() ) {
                foreach ( $apps_query->posts as $app_post_id ) {
                    $this->scheduler->run_single_app_check( [ 'app_post_id' => $app_post_id ] );
                }
            }

            $repos_query = new \WP_Query([
                'post_type'      => 'wp2_repository',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]);

            if ( $repos_query->have_posts() ) {
                foreach ( $repos_query->posts as $repo_post_id ) {
                    $this->scheduler->run_single_repo_check( [ 'repo_post_id' => $repo_post_id ] );
                }
            }

            $this->connection->clear_package_cache();
            delete_transient('wp2_repo_app_map');
            delete_transient('wp2_merged_packages_data');
            wp_update_themes();
            wp_update_plugins();
            do_action( 'wp2_manual_sync_completed' );
            Logger::log( 'Manual scheduler run completed successfully.', 'success', 'tasks' );
        } catch ( \Throwable $exception ) {
            Logger::log( 'Manual scheduler run failed: ' . $exception->getMessage(), 'error', 'tasks' );
            $redirect_url = add_query_arg( 'manual-sync', 'error', admin_url( 'admin.php?page=wp2-update-system-health' ) );
        }

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Handles bulk actions from the Bulk Actions page.
     */
    public function handle_bulk_actions() {
        if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['wp2_bulk_action_nonce'] ) ) {
            wp_die( 'Permission denied.' );
        }
        check_admin_referer( 'wp2_bulk_action', 'wp2_bulk_action_nonce' );

        $action = sanitize_key( $_POST['action'] ?? '' );
        $packages = isset($_POST['packages']) ? array_map('sanitize_text_field', (array) wp_unslash( $_POST['packages'] ) ) : [];

        if ( empty($action) || empty($packages) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=wp2-update-bulk-actions&error=no_action_or_packages' ) );
            exit;
        }

        $count = count($packages);

        switch ($action) {
            case 'force-check':
                $this->connection->clear_package_cache();
                wp_update_themes();
                wp_update_plugins();
                Logger::log( "Bulk action: Forced update check for {$count} packages.", 'info', 'bulk-action' );
                break;
            case 'clear-cache':
                foreach ($packages as $pkg_key) {
                    list($type, $slug) = explode(':', $pkg_key, 2);
                    $repo = 'theme' === $type ? $this->connection->get_managed_themes()[$slug]['repo'] : $this->connection->get_managed_plugins()[$slug]['repo'];
                    delete_transient('wp2_releases_' . md5($repo));
                }
                Logger::log( "Bulk action: Cleared cache for {$count} packages.", 'info', 'bulk-action');
                break;
        }

        // Escape query parameters in redirect URL
        wp_safe_redirect( esc_url( admin_url( "admin.php?page=wp2-update-bulk-actions&success={$action}&count={$count}" ) ) );
        exit;
    }

    /**
     * Handles the installation of a theme version via REST API.
     */
    public function handle_theme_install_action() {
        if ( ! current_user_can( 'install_themes' ) || ! isset( $_POST['_wpnonce'], $_POST['slug'], $_POST['version'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wp2-update' ) ], 403 );
        }

        $slug        = sanitize_key( $_POST['slug'] );
        $version     = sanitize_text_field( $_POST['version'] );
        $package_key = 'theme:' . $slug;
        check_admin_referer( 'wp2_install_theme_' . $slug . '_' . $version );

        $item_data = $this->connection->get_managed_themes()[ $slug ] ?? null;
        if ( ! $item_data ) {
            wp_send_json_error( [ 'message' => __( 'Invalid theme specified.', 'wp2-update' ) ], 400 );
        }

        $result = $this->theme_updater->install_theme( $item_data['app_slug'], $item_data['repo'], $version, $slug );

        if ( is_wp_error( $result ) ) {
            $error_message = sprintf(
                __( 'Failed to install theme %1$s (version %2$s): %3$s', 'wp2-update' ),
                esc_html( $slug ),
                esc_html( $version ),
                esc_html( $result->get_error_message() )
            );
            $this->log_error( $error_message, 'theme-install' );
            wp_send_json_error( [ 'message' => $error_message ], 500 );
        }

        wp_send_json_success( [ 'message' => __( 'Theme installed successfully.', 'wp2-update' ) ] );
    }

    /**
     * Handles the installation of a plugin version via REST API.
     */
    public function handle_plugin_install_action() {
        if ( ! current_user_can( 'install_plugins' ) || ! isset( $_POST['_wpnonce'], $_POST['slug'], $_POST['version'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wp2-update' ) ], 403 );
        }

        $slug        = sanitize_key( $_POST['slug'] );
        $version     = sanitize_text_field( $_POST['version'] );
        $package_key = 'plugin:' . $slug;
        check_admin_referer( 'wp2_install_plugin_' . $slug . '_' . $version );

        $item_data = $this->connection->get_managed_plugins()[ $slug ] ?? null;
        if ( ! $item_data ) {
            wp_send_json_error( [ 'message' => __( 'Invalid plugin specified.', 'wp2-update' ) ], 400 );
        }

        $result = $this->plugin_updater->install_plugin( $item_data['app_slug'], $item_data['repo'], $version, $slug );

        if ( is_wp_error( $result ) ) {
            $error_message = sprintf(
                __( 'Failed to install plugin %1$s (version %2$s): %3$s', 'wp2-update' ),
                esc_html( $slug ),
                esc_html( $version ),
                esc_html( $result->get_error_message() )
            );
            $this->log_error( $error_message, 'plugin-install' );
            wp_send_json_error( [ 'message' => $error_message ], 500 );
        }

        wp_send_json_success( [ 'message' => __( 'Plugin installed successfully.', 'wp2-update' ) ] );
    }
}
