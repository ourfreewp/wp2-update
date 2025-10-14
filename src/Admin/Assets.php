<?php

namespace WP2\Update\Admin;

use WP2\Update\Utils\Logger;

/**
 * Manages the enqueuing of admin-facing scripts and styles.
 * Designed to work with a Vite manifest for modern asset handling.
 */
final class Assets {
    private Data $data;
    private Logger $logger;

    public function __construct(Data $data, Logger $logger) {
        $this->data = $data;
        $this->logger = $logger;
    }

    /**
     * Registers the necessary hooks for enqueuing assets.
     */
    public function register_hooks(): void {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_filter( 'script_loader_tag', [ $this, 'add_module_type_to_script' ], 10, 3 );
    }

    /**
     * Checks if the current page is a plugin screen, then enqueues assets.
     * This is the primary callback for the 'admin_enqueue_scripts' hook.
     * @param string $hook The current admin page hook.
     */
    public function enqueue_assets(string $hook): void {
        error_log('enqueue_assets() called with hook: ' . $hook);

        // A list of screen IDs where our assets should be loaded.
        $allowed_screens = [
            'toplevel_page_wp2-update',
            'wp2-updates_page_wp2-update-github-callback', // Adjusted for submenu page hook
        ];

        // Log the allowed screens for debugging.
        error_log('Allowed screens: ' . implode(', ', $allowed_screens));

        // Ensure assets are enqueued only for plugin-specific admin screens.
        if ( ! in_array( $hook, $allowed_screens, true ) ) {
            error_log('Current screen not allowed: ' . $hook);
            return;
        }

        $manifest = $this->load_manifest();
        if ( ! $manifest ) {
            error_log('Failed to load Vite manifest.');
            // If the manifest is missing, show an admin notice and stop.
            add_action( 'admin_notices', [ self::class, 'render_manifest_error' ] );
            return;
        }

        error_log('Vite manifest loaded successfully.');
        $main_script_handle = 'wp2-update-admin-main';

        $this->enqueue_styles_from_manifest( $manifest );
        $this->enqueue_scripts_from_manifest( $manifest, $main_script_handle );
        $this->localize_script_data( $main_script_handle );
        error_log('Assets enqueued successfully.');

        // Enqueue WordPress dependencies
        wp_enqueue_script('wp-i18n');

        // Log the loading of wp-i18n for debugging
        error_log('wp-i18n script enqueued: ' . (wp_script_is('wp-i18n', 'enqueued') ? 'Yes' : 'No'));
        error_log('wp-i18n script registered: ' . (wp_script_is('wp-i18n', 'registered') ? 'Yes' : 'No'));
        error_log('wp-i18n script done: ' . (wp_script_is('wp-i18n', 'done') ? 'Yes' : 'No'));
    }

    /**
     * Localizes the main script with data from PHP to bootstrap the frontend application.
     * @param string $handle The script handle to attach the data to.
     */
    private function localize_script_data( string $handle ): void {
        $state = $this->data->get_state();

        $data = [
            'nonce'             => wp_create_nonce( 'wp2_get_connection_status' ),
            'apiRoot'           => esc_url_raw( rest_url( \WP2\Update\Config::REST_NAMESPACE ) ),
            'connectionStatus'  => $state['connectionStatus'],
            'apps'              => $state['apps'],
            'packages'          => $state['packages']['all'], // Pass combined packages
            'unlinkedPackages'  => $state['packages']['unlinked'],
            'selectedAppId'     => $state['selectedAppId'],
            'health'            => $state['health'],
            'stats'             => $state['stats'],
            'siteName'          => get_bloginfo('name'),
        ];

        wp_localize_script( $handle, 'wp2UpdateData', $data );
    }

    /**
     * Loads and decodes the Vite manifest file from the 'dist' directory.
     * @return array|null The manifest data or null on failure.
     */
    private function load_manifest(): ?array {
        $manifest_path = WP2_UPDATE_PLUGIN_DIR . '/dist/.vite/manifest.json';

        error_log('Resolved manifest path: ' . $manifest_path);
        error_log('Manifest file exists: ' . (file_exists($manifest_path) ? 'true' : 'false'));

        if ( ! file_exists( $manifest_path ) ) {
            $this->logger::log( 'ERROR', 'Vite manifest file not found at: ' . $manifest_path );
            return null;
        }

        $manifest_contents = file_get_contents( $manifest_path );
        if ( false === $manifest_contents ) {
            $this->logger::log( 'ERROR', 'Failed to read manifest file at: ' . $manifest_path );
            return null;
        }

        $manifest = json_decode( $manifest_contents, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $this->logger::log( 'ERROR', 'Failed to decode manifest JSON: ' . json_last_error_msg() );
            return null;
        }

        return is_array( $manifest ) ? $manifest : null;
    }

    /**
     * Enqueues stylesheets based on the manifest data.
     * @param array $manifest The Vite manifest.
     */
    private function enqueue_styles_from_manifest( array $manifest ): void {
        $entry_key = 'assets/styles/admin-main.scss';
        if ( isset( $manifest[ $entry_key ]['file'] ) ) {
            $file = $manifest[ $entry_key ]['file'];
            wp_enqueue_style(
                'wp2-update-admin-main',
                WP2_UPDATE_PLUGIN_URL . 'dist/' . $file,
                [],
                filemtime( WP2_UPDATE_PLUGIN_DIR . '/dist/' . $file )
            );
        } else {
            $this->logger::log( 'ERROR', 'Admin stylesheet entry missing from manifest.' );
        }
    }

    /**
     * Enqueues JavaScript files based on the manifest data.
     * @param array $manifest The Vite manifest.
     * @param string $handle The script handle.
     */
    private function enqueue_scripts_from_manifest( array $manifest, string $handle ): void {
        $entry_key = 'assets/scripts/admin-main.js';
        if ( isset( $manifest[ $entry_key ]['file'] ) ) {
            $file = $manifest[ $entry_key ]['file'];
            wp_enqueue_script(
                $handle,
                WP2_UPDATE_PLUGIN_URL . 'dist/' . $file,
                ['wp-i18n', 'wp-api-fetch'],
                filemtime( WP2_UPDATE_PLUGIN_DIR . '/dist/' . $file ),
                true // Load in footer
            );

            // Add inline script to verify wp.i18n availability
            wp_add_inline_script(
                $handle,
                'if (typeof wp === "undefined" || !wp.i18n) {
                    console.error("wp.i18n is not available.");
                } else {
                    console.log("wp.i18n is loaded.");
                }'
            );

            // Add inline script to verify wp.apiFetch availability
            wp_add_inline_script(
                $handle,
                'if (typeof wp === "undefined" || !wp.apiFetch) {
                    console.error("wp.apiFetch is not available.");
                } else {
                    console.log("wp.apiFetch is loaded.");
                }'
            );

            // Log the enqueued script details
            $this->logger::log('INFO', 'Enqueued script: ' . $file);
        } else {
            $this->logger::log( 'ERROR', 'Admin script entry missing from manifest.' );
        }
    }

    /**
     * Adds type="module" to the script tag for modern JavaScript.
     * @param string $tag The original script tag.
     * @param string $handle The script's handle.
     * @return string The modified script tag.
     */
    public function add_module_type_to_script( string $tag, string $handle, string $src ): string {
        if ( 'wp2-update-admin-main' === $handle ) {
            return '<script type="module" src="' . esc_url( $src ) . '"></script>';
        }
        return $tag;
    }

    /**
     * Renders an admin notice when the asset manifest is missing.
     */
    public static function render_manifest_error(): void {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'The Vite manifest file is missing. Please rebuild the plugin assets.', \WP2\Update\Config::TEXT_DOMAIN ) . '</p></div>';
    }
}
