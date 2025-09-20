<?php
namespace WP2\Update\Admin\Actions;

use WP2\Update\Core\Connection\Init as Connection;
use WP2\Update\Core\GitHubApp\Init as GitHubApp;
use WP2\Update\Core\Updates\PluginUpdater;
use WP2\Update\Core\Updates\ThemeUpdater;
use WP2\Update\Core\Utils\Logger;
use WP2\Update\Core\Utils\Init as SharedUtils;
use WP2\Update\Core\Tasks\Scheduler;

/**
 * Handles all admin-side actions, such as form submissions and GitHubApp callbacks.
 */
class Controller {
    private $connection;
    private $github_app;
    private $theme_updater;
    private $plugin_updater;
    private $utils;

    /**
     * Constructor.
     */
    public function __construct( Connection $connection, GitHubApp $github_app, ThemeUpdater $theme_updater, PluginUpdater $plugin_updater, SharedUtils $utils ) {
        $this->connection     = $connection;
        $this->github_app     = $github_app;
        $this->theme_updater  = $theme_updater;
        $this->plugin_updater = $plugin_updater;
        $this->utils          = $utils;
    }

    /**
     * Logs GitHub App-related actions for debugging.
     */
    private function log_github_app_action($message, $data = []) {
        Logger::log(
            'GitHub App Debug',
            $message,
            $data
        );
    }
    /**
     * Main action router for events on standard admin pages.
     */
    public function handle_admin_actions() {
        if ( ! current_user_can( 'manage_options' ) || empty( $_GET['page'] ) || strpos((string)$_GET['page'], 'wp2-update') === false ) {
            return;
        }

        // Example: handle disconnect action
        if ( isset( $_GET['action'] ) && 'disconnect' === $_GET['action'] ) {
            $this->log_github_app_action('Disconnected from GitHub App');
            set_transient('wp2_update_admin_notice', __('Disconnected from GitHub.', 'wp2-update'), 60);
            wp_safe_redirect(admin_url('admin.php?page=wp2-update-settings&disconnected=1'));
            exit;
        }

        // Handle connected=1 parameter
        if ( isset( $_GET['connected'] ) && '1' === $_GET['connected'] ) {
            $this->log_github_app_action('Connected parameter detected', ['connected' => $_GET['connected']]);
            set_transient( 'wp2_update_admin_notice', __( 'Successfully connected to GitHub!', 'wp2-update' ), 60 );
        }

        // Handle the force-check action
        if ( isset( $_GET['force-check'] ) && '1' === $_GET['force-check'] ) {
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'wp2-force-check' ) ) {
                wp_die( 'Security check failed.' );
            }

            // Clear transients and force a check
            delete_site_transient( 'update_themes' );
            delete_site_transient( 'update_plugins' );
            wp_update_themes();
            wp_update_plugins();

            // Redirect back with a success message
            wp_safe_redirect( admin_url( 'admin.php?page=wp2-update-system-health&cache-cleared=1' ) );
            exit;
        }
    }

    /**
     * Handles bulk actions from the Bulk Actions page.
     */
    public function handle_bulk_actions() {
        if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['wp2_bulk_action_nonce'] ) ) {
            wp_die( 'Permission denied.' );
        }
        check_admin_referer( 'wp2_bulk_action', 'wp2_bulk_action_nonce' );

        $action = sanitize_key( $_POST['bulk-action'] ?? '' );
        $packages = isset($_POST['packages']) ? array_map('sanitize_text_field', $_POST['packages']) : [];

        if ( empty($action) || empty($packages) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=wp2-update-bulk-actions&error=no_action_or_packages' ) );
            exit;
        }

        $count = count($packages);

        switch ($action) {
            case 'force-check':
                delete_site_transient('update_themes');
                delete_site_transient('update_plugins');
                Logger::log( "Bulk action: Forced update check for {$count} packages.", 'info', 'bulk-action', 'Package' );
                break;
            case 'clear-cache':
                foreach ($packages as $pkg_key) {
                    list($type, $slug) = explode(':', $pkg_key, 2);
                    $repo = 'theme' === $type ? $this->connection->get_managed_themes()[$slug]['repo'] : $this->connection->get_managed_plugins()[$slug]['repo'];
                    delete_transient('wp2_releases_' . md5($repo));
                }
                Logger::log( "Bulk action: Cleared cache for {$count} packages.", 'info', 'bulk-action', 'Package' );
                break;
        }

        wp_safe_redirect( admin_url( "admin.php?page=wp2-update-bulk-actions&success={$action}&count={$count}" ) );
        exit;
    }

    /**
     * Handles the installation of a theme version.
     */
    public function handle_theme_install_action() {
        if ( ! current_user_can( 'install_themes' ) || ! isset( $_POST['_wpnonce'], $_POST['slug'], $_POST['version'] ) ) {
            wp_die( __( 'You do not have permission to install themes.', 'wp2-update' ) );
        }

        $slug        = sanitize_key( $_POST['slug'] );
        $version     = sanitize_text_field( $_POST['version'] );
        $package_key = 'theme:' . $slug;
        check_admin_referer( 'wp2_install_theme_' . $slug . '_' . $version );

        $item_data = $this->connection->get_managed_themes()[ $slug ] ?? null;
        if ( ! $item_data ) {
            wp_die( __( 'Invalid theme specified.', 'wp2-update' ) );
        }

        // Pass the app slug and repo slug to the updater.
        $result = $this->theme_updater->install_theme( $item_data['app_slug'], $item_data['repo'], $version );

        $redirect_url = admin_url( 'admin.php?page=wp2-update-packages&package=' . urlencode( $package_key ) );
        if ( is_wp_error( $result ) ) {
            set_transient( 'wp2_update_error_notice', $result->get_error_message(), 60 );
            $redirect_url .= '&error=install_failed';
        } else {
            $redirect_url .= '&installed=' . urlencode( $version );
        }
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Handles the installation of a plugin version.
     */
    public function handle_plugin_install_action() {
        if ( ! current_user_can( 'install_plugins' ) || ! isset( $_POST['_wpnonce'], $_POST['slug'], $_POST['version'] ) ) {
            wp_die( __( 'You do not have permission to install plugins.', 'wp2-update' ) );
        }

        $slug        = sanitize_key( $_POST['slug'] );
        $version     = sanitize_text_field( $_POST['version'] );
        $package_key = 'plugin:' . $slug;
        check_admin_referer( 'wp2_install_plugin_' . $slug . '_' . $version );

        $item_data = $this->connection->get_managed_plugins()[ $slug ] ?? null;
        if ( ! $item_data ) {
            wp_die( __( 'Invalid plugin specified.', 'wp2-update' ) );
        }

        // Pass the app slug and repo slug to the updater.
        $result = $this->plugin_updater->install_plugin( $item_data['app_slug'], $item_data['repo'], $version );

        $redirect_url = admin_url( 'admin.php?page=wp2-update-packages&package=' . urlencode( $package_key ) );
        if ( is_wp_error( $result ) ) {
            set_transient( 'wp2_update_error_notice', $result->get_error_message(), 60 );
            $redirect_url .= '&error=install_failed';
        } else {
            $redirect_url .= '&installed=' . urlencode( $version );
            $redirect_url = add_query_arg( 'wp2_notice_nonce', wp_create_nonce( 'wp2_install_success_' . $slug ), $redirect_url );
        }
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Handles the connection test action.
     */
    public function handle_test_connection_action() {
        if ( ! current_user_can( 'manage_options' ) || !isset($_POST['app_id']) || !isset($_POST['_wpnonce']) ) {
            wp_die( __( 'You do not have permission to perform this action.', 'wp2-update' ) );
        }

        $app_id = sanitize_key($_POST['app_id']);
        check_admin_referer('wp2_test_connection_' . $app_id, '_wpnonce');

        $app_post = get_post($app_id);
        if (!$app_post || $app_post->post_type !== 'wp2_github_app') {
            wp_die(__('Invalid app ID.', 'wp2-update'));
        }

        $app_slug = $app_post->post_name;
        $success = $this->github_app->test_connection($app_slug);

        if ( $success ) {
            set_transient( 'wp2_update_admin_notice', __( 'Connection test successful.', 'wp2-update' ), 60 );
        } else {
            set_transient( 'wp2_update_admin_notice', __( 'Connection test failed. Please check your GitHub App settings.', 'wp2-update' ), 60 );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=wp2-update-settings' ) );
        exit;
    }
}
