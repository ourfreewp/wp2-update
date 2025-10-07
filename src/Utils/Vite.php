<?php

namespace WP2\Update\Utils;

class Vite
{
    public function __construct()
    {
        // Use a closure to call the enqueueProdAssets method with the correct entry point.
        add_action('admin_enqueue_scripts', function() {
            global $pagenow;

            // Only enqueue assets on WP2 Update admin pages
            if ($this->isWp2UpdateAdminPage()) {
                $this->enqueueProdAssets('assets/scripts/admin-main.js');
                $this->enqueueAdditionalStyles();

                // Localize the REST API nonce for frontend scripts
                wp_localize_script(
                    'vite-main-script',
                    'wpApiSettings',
                    [
                        'root'  => esc_url_raw( rest_url() ),
                        'nonce' => wp_create_nonce( 'wp_rest' ),
                    ]
                );
            }
        });
    }

    /**
     * Checks if the current admin page belongs to WP2 Update plugin or CPT edit screens.
     *
     * @return bool True if the current page is relevant, false otherwise.
     */
    private function isWp2UpdateAdminPage(): bool
    {
        // Check for WP2 Update plugin pages
        if (isset($_GET['page']) && strpos($_GET['page'], 'wp2-update') === 0) {
            return true;
        }

        // Check for CPT edit screens
        $current_screen = get_current_screen();
        if ($current_screen && in_array($current_screen->post_type, ['custom_post_type_1', 'custom_post_type_2'], true)) {
            return true;
        }

        return false;
    }

    /**
     * Enqueues the built assets for production.
     *
     * @param string $entry The entry point from the Vite manifest.
     */
    public function enqueueProdAssets(string $entry)
    {
        $manifest_path = WP2_UPDATE_PLUGIN_DIR . '/dist/.vite/manifest.json';

        if (!file_exists($manifest_path)) {
            error_log('Vite manifest not found at: ' . $manifest_path);
            return;
        }

        $manifest = json_decode(file_get_contents($manifest_path), true);

        if (isset($manifest[$entry])) {
            $entry_manifest = $manifest[$entry];

            // Enqueue the main JavaScript file
            if (isset($entry_manifest['file'])) {
                wp_enqueue_script(
                    'vite-main-script',
                    WP2_UPDATE_PLUGIN_URL . 'dist/' . $entry_manifest['file'],
                    ['wp-api'],
                    null,
                    true
                );
            }

            // Enqueue associated CSS files
            if (isset($entry_manifest['css'])) {
                foreach ($entry_manifest['css'] as $css_file) {
                    $css_url = WP2_UPDATE_PLUGIN_URL . 'dist/' . $css_file;
                    wp_enqueue_style(
                        'vite-style-' . basename($css_file),
                        $css_url,
                        [],
                        null
                    );
                }
            }
        } else {
            error_log('Vite: Entry not found in manifest: ' . $entry);
        }
    }

    /**
     * Enqueues additional SCSS bundles and styles.
     */
    private function enqueueAdditionalStyles()
    {
        // Enqueue SCSS bundle
        wp_enqueue_style(
            'vite-admin-main-style',
            WP2_UPDATE_PLUGIN_URL . 'assets/styles/admin-main.css',
            [],
            null
        );

        // Enqueue Toastify CSS
        wp_enqueue_style(
            'vite-toastify-style',
            WP2_UPDATE_PLUGIN_URL . 'assets/styles/toastify.css',
            [],
            null
        );
    }
}