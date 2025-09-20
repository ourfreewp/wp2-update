<?php
namespace WP2\Update\Core\Updates;

use WP2\Update\Core\Connection\Init as Connection;
use WP2\Update\Core\GitHubApp\Init as GitHubApp;
use WP2\Update\Core\Utils\Init as SharedUtils;
use WP2\Update\Core\Utils\Logger;
use WP_Error;

/**
 * Handles the integration with the WordPress plugin update system.
 */
class PluginUpdater {
    /** @var Connection The connection handler. */
    private $connection;

    /** @var GitHubApp The GitHub App handler. */
    private $github_app;

    /** @var SharedUtils The shared updater utilities. */
    private $utils;

    /**
     * Constructor.
     */
    public function __construct( Connection $connection, GitHubApp $github_app, SharedUtils $utils ) {
        $this->connection = $connection;
        $this->github_app = $github_app;
        $this->utils      = $utils;
    }

    /**
     * Checks for plugin updates by querying the GitHub API.
     * This function has been refactored to use the multi-app API.
     *
     * @param object $transient The update plugins transient.
     * @return object The modified transient.
     */
    public function check_for_updates( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $managed_plugins = $this->connection->get_managed_plugins();
        foreach ($managed_plugins as $slug => $item) {
            if (empty($item['repo']) || empty($item['app_slug'])) {
                continue;
            }

            // Correctly use the GitHubApp service for the specific app slug.
            $response = $this->github_app->gh($item['app_slug'], 'GET', "/repos/{$item['repo']}/releases/latest");

            if (!$response['ok'] || empty($response['data'])) {
                Logger::log("Failed to fetch updates for {$item['repo']} from app {$item['app_slug']}.", 'error', 'update');
                continue;
            }

            $latest_release = $response['data'];
            $current_version = get_plugin_data(WP_PLUGIN_DIR . '/' . $slug)['Version'];
            $new_version = self::normalize_version($latest_release['tag_name'] ?? '0');

            if (version_compare($new_version, $current_version, '>')) {
                $package_info = (object) [
                    'slug'        => $slug,
                    'new_version' => $new_version,
                    'url'         => $latest_release['html_url'],
                    'package'     => $latest_release['zipball_url'],
                ];
                $transient->response[$slug] = $package_info;
            }
        }
        return $transient;
    }

    public static function normalize_version(string $version): string {
        return ltrim($version, 'v');
    }

    /**
     * Installs a specific version of a plugin from a GitHub repository.
     * This method has been refactored to use the multi-app API.
     *
     * @param string $app_slug The app slug to use for authentication.
     * @param string $repo     The repository name ("owner/repo").
     * @param string $version  The tag name to install.
     * @return true|WP_Error True on success, WP_Error on failure.
     */
    public function install_plugin( string $app_slug, string $repo, string $version ) {
        Logger::log( "Attempting to install plugin {$repo} version {$version} using app {$app_slug}.", 'info', 'install' );

        $release_res = $this->github_app->gh($app_slug, 'GET', "/repos/{$repo}/releases/tags/{$version}");
        if (empty($release_res['ok'])) {
            Logger::log("Install failed: Could not fetch release info for tag {$version}. Error: " . ($release_res['error'] ?? 'Unknown'), 'error', 'install');
            return new WP_Error('release_fetch_failed', __('Could not fetch release information from GitHub.', 'wp2-update'));
        }

        $zip_url = $this->utils->get_zip_url_from_release( $release_res['data'] );
        if ( ! $zip_url ) {
            Logger::log( "Install failed: No ZIP asset found for tag {$version}.", 'error', 'install' );
            return new WP_Error( 'no_zip_asset', __( 'The selected release does not contain a valid ZIP file asset.', 'wp2-update' ) );
        }

        if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
            Logger::log( 'Install failed: File modifications are disabled (DISALLOW_FILE_MODS).', 'error', 'install' );
            return new WP_Error( 'file_mods_disabled', __( 'File modifications are disabled in your WordPress configuration.', 'wp2-update' ) );
        }

        // Load WordPress Core files required for plugin installation.
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

        $slug = basename( $repo );

        $upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
        $result   = $upgrader->install( $zip_url, [ 'overwrite_package' => true ] );

        if ( is_wp_error( $result ) ) {
            Logger::log( "Install failed: WP_Upgrader returned an error. Message: " . $result->get_error_message(), 'error', 'install' );
            return $result;
        }

        // Clear caches after a successful installation.
        wp_clean_plugins_cache( true );
        delete_site_transient( 'update_plugins' );

        Logger::log( "Plugin {$slug} version {$version} installed successfully.", 'success', 'install' );
        return true;
    }

    /**
     * Registers WordPress hooks for plugin updates.
     */
    public function register_hooks() {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_updates']);
    }
}
