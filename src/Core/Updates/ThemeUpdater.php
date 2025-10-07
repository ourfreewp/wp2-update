<?php
namespace WP2\Update\Core\Updates;

use WP2\Update\Core\Connection\Init as Connection;
use WP2\Update\Core\API\GitHubApp\Init as GitHubApp;
use WP2\Update\Core\API\Service as GitHubService;
use WP2\Update\Utils\SharedUtils;
use WP2\Update\Utils\Logger;
use WP_Error;

/**
 * Handles the integration with the WordPress theme update system.
 */
class ThemeUpdater {
    /** @var Connection The connection handler. */
    private $connection;

    /** @var GitHubApp The GitHub App handler. */
    private $github_app;

    /** @var SharedUtils The shared updater utilities. */
    private $utils;

    /** @var GitHubService The GitHub service for authenticated requests. */
    private $github_service;

    /**
     * Constructor.
     */
    public function __construct( Connection $connection, GitHubApp $github_app, SharedUtils $utils, GitHubService $github_service ) {
        $this->connection = $connection;
        $this->github_app = $github_app;
        $this->utils      = $utils;
        $this->github_service = $github_service;
    }

    /**
     * Checks for theme updates by querying the GitHub API.
     * This function has been refactored to use the multi-app API.
     *
     * @param object $transient The update themes transient.
     * @return object The modified transient.
     */
    public function check_for_updates( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $managed_themes = $this->connection->get_managed_themes();
        foreach ($managed_themes as $slug => $item) {
            if (empty($item['repo']) || empty($item['app_slug'])) {
                continue;
            }

            // Correctly use the GitHubApp service for the specific app slug.
            $response = $this->github_app->gh($item['app_slug'], 'GET', "/repos/{$item['repo']}/releases/latest");

            if (!$response['ok'] || empty($response['data'])) {
                Logger::log("Failed to fetch updates for theme {$item['repo']} from app {$item['app_slug']}.", 'error', 'update');
                continue;
            }

            $latest_release = $response['data'];
            $current_version = wp_get_theme($slug)->get('Version');
            $new_version = $this->utils->normalize_version( $latest_release['tag_name'] ?? '0' );

            if (version_compare($new_version, $current_version, '>')) {
                $package_info = [
                    'theme'       => $slug,
                    'new_version' => $new_version,
                    'url'         => $latest_release['html_url'],
                    'package'     => $latest_release['zipball_url'],
                ];
                $transient->response[$slug] = $package_info;
            }
        }
        return $transient;
    }

    /**
     * Installs a specific version of a theme from a GitHub repository.
     * This method has been refactored to use the multi-app API.
     *
     * @param string $app_slug The app slug to use for authentication.
     * @param string $repo     The repository name ("owner/repo").
     * @param string $version  The tag name to install.
     * @param string $slug     The theme slug (directory name).
     * @return true|WP_Error True on success, WP_Error on failure.
     */
    public function install_theme( string $app_slug, string $repo, string $version, string $slug ) {
        // The slug isn't used here, as theme activation is a manual user step,
        // but it's included for method signature consistency.
        return $this->install_package( $app_slug, $repo, $version, 'theme' );
    }

    /**
     * Unified installation logic for themes and plugins.
     *
     * @param string $app_slug The app slug to use for authentication.
     * @param string $repo     The repository name ("owner/repo").
     * @param string $version  The tag name to install.
     * @param string $type     The type of package ("theme" or "plugin").
     * @return true|WP_Error True on success, WP_Error on failure.
     */
    private function install_package( string $app_slug, string $repo, string $version, string $type ) {
        Logger::log( "Attempting to install {$type} {$repo} version {$version} using app {$app_slug}.", 'info', 'install' );

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

        // Load WordPress Core files required for installation.
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        if ( $type === 'theme' ) {
            require_once ABSPATH . 'wp-admin/includes/theme.php';
            $skin = new \Automatic_Upgrader_Skin();
            $upgrader = new \Theme_Upgrader( $skin );
        } else {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            $skin = new \Automatic_Upgrader_Skin();
            $upgrader = new \Plugin_Upgrader( $skin );
        }

        // Use authenticated client to download the file.
        $temp_zip_file = $this->github_service->download_to_temp_file( $app_slug, $zip_url );
        if ( is_wp_error( $temp_zip_file ) ) {
            return $temp_zip_file;
        }

        // Install the package.
        $result = $upgrader->install( $temp_zip_file );
        if ( is_wp_error( $result ) ) {
            Logger::log( "Install failed: " . $result->get_error_message(), 'error', 'install' );
            return $result;
        }

        Logger::log( ucfirst($type) . " {$repo} version {$version} installed successfully.", 'success', 'install');
        return true;
    }

    /**
     * Registers WordPress hooks for theme updates.
     */
    public function register_hooks() {
        add_filter('pre_set_site_transient_update_themes', [$this, 'check_for_updates']);
        add_filter('upgrader_pre_download', [$this, 'maybe_provide_authenticated_package'], 10, 4);
    }

    /**
     * Provides an authenticated download for private GitHub theme packages.
     *
     * @param mixed               $reply
     * @param string              $package
     * @param \WP_Upgrader        $upgrader
     * @param array<string,mixed> $hook_extra
     * @return mixed
     */
    public function maybe_provide_authenticated_package( $reply, string $package, $upgrader, array $hook_extra ) {
        if ( empty( $hook_extra['theme'] ) ) {
            return $reply;
        }

        $theme_slug = $hook_extra['theme'];
        $managed_themes = $this->connection->get_managed_themes();

        if ( ! isset( $managed_themes[ $theme_slug ] ) ) {
            return $reply;
        }

        $managed = $managed_themes[ $theme_slug ];
        $app_slug = $managed['app_slug'] ?? '';

        if ( '' === $app_slug ) {
            return $reply;
        }

        $temp_file = $this->github_service->download_to_temp_file( $app_slug, $package );

        if ( is_wp_error( $temp_file ) ) {
            Logger::log( 'Authenticated download failed for theme ' . $theme_slug . ': ' . $temp_file->get_error_message(), 'error', 'install' );
            return $temp_file;
        }

        return $temp_file;
    }

    /**
     * Updates a theme with pre-checks for active status.
     *
     * @param string $theme_slug The slug of the theme to update.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function update_theme_with_checks( string $theme_slug ) {
        // Check if the theme is active.
        $current_theme = wp_get_theme();
        if ( $current_theme->get_stylesheet() === $theme_slug ) {
            // Warn the user and confirm the update.
            if ( ! $this->confirm_active_theme_update( $theme_slug ) ) {
                return new WP_Error(
                    'wp2_update_cancelled',
                    __( 'Update cancelled by user.', 'wp2-update' )
                );
            }
        }

        // Proceed with the update.
        return $this->update_theme( $theme_slug );
    }

    /**
     * Confirms if the user wants to update the active theme.
     *
     * @param string $theme_slug The slug of the active theme.
     * @return bool True if the user confirms, false otherwise.
     */
    private function confirm_active_theme_update( string $theme_slug ): bool {
        // Logic to display a confirmation dialog to the user.
        // This is a placeholder for the actual implementation.
        return true;
    }

    /**
     * Updates a theme.
     *
     * @param string $theme_slug The slug of the theme to update.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    private function update_theme( string $theme_slug ) {
        // Logic for updating the theme goes here.
        // This is a placeholder for the actual update logic.
        return true;
    }
}
