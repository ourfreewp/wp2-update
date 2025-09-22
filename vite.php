<?php

class Vite
{
    public function __construct()
    {
        // Use a closure to call the enqueueProdAssets method with the correct entry point.
        add_action('admin_enqueue_scripts', function() {
            $this->enqueueProdAssets('assets/scripts/admin-main.js');
        });
    }

    /**
     * Enqueues the built assets for production.
     *
     * @param string $entry The entry point from the Vite manifest.
     */
    public function enqueueProdAssets(string $entry)
    {
        $manifest_path = plugin_dir_path(__FILE__) . 'dist/.vite/manifest.json';

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
                    plugin_dir_url(__FILE__) . 'dist/' . $entry_manifest['file'],
                    ['wp-api'],
                    null,
                    true
                );
            }

            // Enqueue associated CSS files
            if (isset($entry_manifest['css'])) {
                foreach ($entry_manifest['css'] as $css_file) {
                    $css_url = plugin_dir_url(__FILE__) . 'dist/' . $css_file;
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
}
