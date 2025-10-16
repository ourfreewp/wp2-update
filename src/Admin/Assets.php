<?php

namespace WP2\Update\Admin;

use WP2\Update\Utils\Logger;
use WP2\Update\Config;


/**
 * Manages the enqueuing of admin-facing scripts and styles.
 * Designed to work with a Vite manifest for modern asset handling.
 */
final class Assets {
    private Data $data;

    public function __construct(Data $data) {
        $this->data = $data;
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
        Logger::info('Attempting to enqueue assets.', ['hook' => $hook]);

        $allowed_screens = [
            'toplevel_page_wp2-update',
            'wp2-updates_page_wp2-update-github-callback',
        ];

        if (!in_array($hook, $allowed_screens, true)) {
            Logger::warning('Assets not enqueued: Hook not allowed.', ['hook' => $hook]);
            return;
        }

        $manifest = $this->load_manifest();
        if (!$manifest) {
            Logger::error('Assets not enqueued: Manifest file missing.');
            add_action('admin_notices', [self::class, 'render_manifest_error']);
            return;
        }

        Logger::info('Enqueuing assets.', ['manifest' => $manifest]);
        $main_script_handle = 'wp2-update-admin-main';

        $this->enqueue_styles_from_manifest( $manifest );
        $this->enqueue_scripts_from_manifest( $manifest, $main_script_handle );
        $this->localize_script_data( $main_script_handle );

        // Enqueue WordPress dependencies
        wp_enqueue_script('wp-i18n');

        // Ensure wp-api-fetch is registered with dependencies
        wp_register_script('wp-api-fetch', false, ['wp-i18n'], null, true);
        wp_enqueue_script('wp-api-fetch');
        // Localize REST API root and nonce for use in JavaScript
        wp_localize_script(
            $main_script_handle,
            'wpApiSettings',
            [
                'root'  => esc_url_raw(rest_url()),
                'nonce' => wp_create_nonce('wp_rest'),
            ]
        );
    }

    /**
     * Localizes the main script with data from PHP to bootstrap the frontend application.
     * @param string $handle The script handle to attach the data to.
     */
    private function localize_script_data( string $handle ): void {
        $state = $this->data->get_state();

        // Ensure WordPress environment is fully loaded.
        if ( ! function_exists( 'add_action' ) ) {
            Logger::error( 'WordPress environment is not fully loaded. Skipping asset localization.' );
            return;
        }

        $data = [
            'nonce'             => wp_create_nonce( 'wp2_get_connection_status' ),
            'apiRoot'           => esc_url_raw( rest_url( Config::REST_NAMESPACE ) ),
            'connectionStatus'  => $state['connectionStatus'],
            'apps'              => $state['apps'],
            'packages'          => $state['packages']['all'], // Pass combined packages
            'unlinkedPackages'  => $state['packages']['unlinked'],
            'selectedAppId'     => $state['selectedAppId'],
            'health'            => $state['health'],
            'stats'             => $state['stats'],
            'siteName'          => get_bloginfo('name'),
            'i18n'              => [
                'loading' => __( 'Loading', 'wp2-update' ),
                'appInitialized' => __( 'Application already initialized. Skipping.', 'wp2-update' ),
                'domLoaded' => __( 'DOM Content Loaded. Initializing WP2 Update application.', 'wp2-update' ),
                'syncFailed' => __( 'Failed during initial data synchronization:', 'wp2-update' ),
                'loadDataError' => __( 'Failed to load initial data.', 'wp2-update' ),
                'appInteraction' => __( 'App interaction triggered for App ID:', 'wp2-update' ),
                'loadMore' => __( 'Load More', 'wp2-update' ),
                'selectAction' => __( 'Please select an action and at least one package.', 'wp2-update' ),
                'invalidAction' => __( 'Invalid action selected.', 'wp2-update' ),
                'selectGitHubApp' => __( 'Select a GitHub App to manage this package.', 'wp2-update' ),
                'assignAppTitle' => __( 'Assign App to', 'wp2-update' ),
                'rollbackTitle' => __( 'Rollback', 'wp2-update' ),
                'rollbackSelectVersion' => __( 'Select a version to rollback to.', 'wp2-update' ),
                'rollbackCancel' => __( 'Cancel', 'wp2-update' ),
                'rollbackConfirm' => __( 'Confirm Rollback', 'wp2-update' ),
                'rollbackSuccess' => __( 'Rollback successful!', 'wp2-update' ),
                'rollbackFailed' => __( 'Failed to rollback. Please try again.', 'wp2-update' ),
                'noReleaseNotes' => __( 'No release notes available.', 'wp2-update' ),
                'fetchReleaseNotesError' => __( 'Failed to fetch release notes. Please try again.', 'wp2-update' ),
            ],
        ];

        wp_localize_script( $handle, 'wp2UpdateData', $data );
    }

    /**
     * Loads and decodes the Vite manifest file from the 'dist' directory.
     * @return array|null The manifest data or null on failure.
     */
    private function load_manifest(): ?array {
        $manifest_path = WP2_UPDATE_PLUGIN_DIR . '/dist/.vite/manifest.json';

        if ( ! file_exists( $manifest_path ) ) {
            return null;
        }

        $manifest_contents = file_get_contents( $manifest_path );
        if ( false === $manifest_contents ) {
            return null;
        }

        $manifest = json_decode( $manifest_contents, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
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
        echo '<div class="notice notice-error"><p>' . esc_html__( 'The Vite manifest file is missing. Please rebuild the plugin assets.', Config::TEXT_DOMAIN ) . '</p></div>';
    }
}
