<?php

namespace WP2\Update\Admin\Assets;

/**
 * Manages the enqueuing of admin-facing scripts and styles.
 * Designed to work with a Vite manifest for modern asset handling.
 */
final class Manager {

    /**
     * Registers the necessary hooks for enqueuing assets.
     */
    public static function register_hooks(): void {
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
    }

    /**
     * Checks if the current page is a plugin screen, then enqueues assets.
     * This is the primary callback for the 'admin_enqueue_scripts' hook.
     */
    public static function enqueue_assets(): void {
        // Abort if we're not on a screen belonging to our plugin.
        if ( ! self::is_plugin_screen() ) {
            return;
        }

        $manifest = self::load_manifest();
        if ( ! $manifest ) {
            // If the manifest is missing, show an error and stop.
            add_action( 'admin_notices', [ self::class, 'render_manifest_error' ] );
            return;
        }

        $main_script_handle = 'wp2-update-admin-main';

        self::enqueue_styles_from_manifest( $manifest );
        self::enqueue_scripts_from_manifest( $manifest, $main_script_handle );
        self::localize_script_data( $main_script_handle );
    }

    /**
     * Checks if the current admin screen belongs to this plugin.
     */
    private static function is_plugin_screen(): bool {
        if ( ! function_exists( 'get_current_screen' ) ) {
            return false;
        }
        $screen = get_current_screen();
        if ( ! $screen ) {
            return false;
        }

        // List of screen IDs where our assets should be loaded.
        $allowed_screens = [
            'toplevel_page_wp2-update',
            'admin_page_wp2-update-github-callback',
        ];

        return in_array( $screen->id, $allowed_screens, true );
    }

    /**
     * Logs errors related to the Vite manifest.
     */
    private static function log_manifest_error( string $message ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[WP2-Update] Manifest Error: ' . $message );
        }
    }

    /**
     * Loads and decodes the Vite manifest file.
     *
     * @return array|null The manifest data or null on failure.
     */
    private static function load_manifest(): ?array {
        $manifest_path = trailingslashit( WP2_UPDATE_PLUGIN_DIR ) . 'dist/manifest.json';
        if ( ! file_exists( $manifest_path ) ) {
            self::log_manifest_error( 'Manifest file not found at ' . $manifest_path );
            return null;
        }

        $manifest_contents = file_get_contents( $manifest_path );
        if ( false === $manifest_contents ) {
            self::log_manifest_error( 'Failed to read manifest file at ' . $manifest_path );
            return null;
        }

        $manifest = json_decode( $manifest_contents, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            self::log_manifest_error( 'Manifest JSON decoding error: ' . json_last_error_msg() );
            return null;
        }

        return $manifest;
    }

    /**
     * Enqueues stylesheets based on the manifest data.
     */
    private static function enqueue_styles_from_manifest( array $manifest ): void {
        $style_entry = 'assets/styles/admin-main.scss';

        if ( ! empty( $manifest[ $style_entry ]['file'] ) ) {
            wp_enqueue_style(
                'wp2-update-admin-main',
                WP2_UPDATE_PLUGIN_URL . 'dist/' . $manifest[ $style_entry ]['file'],
                [],
                null // Versioning is handled by the hashed filename from Vite.
            );
        }
    }

    /**
     * Enqueues JavaScript files based on the manifest data.
     */
    private static function enqueue_scripts_from_manifest( array $manifest, string $handle ): void {
        $script_entry = 'assets/scripts/admin-main.js';

        if ( ! empty( $manifest[ $script_entry ]['file'] ) ) {
            wp_enqueue_script(
                $handle,
                WP2_UPDATE_PLUGIN_URL . 'dist/' . $manifest[ $script_entry ]['file'],
                [ 'wp-i18n' ], // WordPress internationalization as a dependency.
                null, // Versioning is handled by the hashed filename from Vite.
                true
            );
        }
    }

    /**
     * Localizes the main script with data from PHP.
     */
    private static function localize_script_data( string $handle ): void {
        $callback_url = admin_url( 'admin.php?page=wp2-update-github-callback' );
        $redirect_url = admin_url( 'admin.php?page=wp2-update' );

        $data = [
            'apiRoot'   => esc_url_raw( rest_url( 'wp2-update/v1/' ) ),
            'nonce'     => wp_create_nonce( 'wp_rest' ),
            'siteName'  => get_bloginfo( 'name' ),
            'redirectUrl' => esc_url_raw( $redirect_url ),
            'githubAppManifest' => wp_json_encode(
                [
                    'name'                => get_bloginfo( 'name' ) . ' Updater',
                    'url'                 => home_url(),
                    'public'              => false,
                    'callback_urls'       => [ home_url() ],
                    'redirect_url'        => $callback_url,
                    'setup_url'           => $redirect_url,
                    'setup_on_update'     => false,
                    'default_permissions' => [
                        'contents' => 'read',
                        'metadata' => 'read',
                    ],
                    'default_events'      => [ 'release' ],
                ],
                JSON_UNESCAPED_SLASHES
            ),
        ];

        wp_localize_script( $handle, 'wp2UpdateData', $data );
    }

    /**
     * Renders an admin notice when the asset manifest is missing.
     */
    public static function render_manifest_error(): void {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e( 'Failed to load asset manifest for WP2 Update. Please run your build process.', 'wp2-update' ); ?></p>
        </div>
        <?php
    }
}
