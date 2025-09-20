<?php

class Vite
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', function () {
            $this->enqueueProdAssets('assets/scripts/admin-main.js');
        });

        add_action('admin_enqueue_scripts', function () {
            $this->enqueueProdAssets('assets/scripts/admin-main.js');
        });
    }

    /**
     * Enqueues the assets for the front-end.
     */
    public function enqueueAssets()
    {
        // Adjust the entry point to match your file structure
        $this->enqueue('src/main.js');

    }

    /**
     * Enqueues the assets for the WordPress admin area.
     */
    public function enqueueAdminAssets()
    {
        // Enqueue admin-specific assets using the manifest.json
        $this->enqueue('assets/scripts/admin-main.js');


    }

    /**
     * The main enqueueing logic.
     *
     * @param string $entry The entry point file (e.g., 'src/main.js').
     */
    private function enqueue(string $entry)
    {
        // In development, load assets from the Vite dev server
        if ($this->isViteDevServerRunning()) {
            $this->enqueueDevAssets($entry);
        }
        // In production, load the built assets
        else {
            $this->enqueueProdAssets($entry);
        }


    }

    /**
     * Checks if the Vite development server is active.
     */
    private function isViteDevServerRunning(): bool
    {
        // Define WP_DEBUG as true in your wp-config.php for local development
        return defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Enqueues assets from the Vite development server.
     */
    private function enqueueDevAssets(string $entry)
    {
        $dev_server_url = 'http://localhost:5173';

        // Enqueue the Vite client for HMR with type="module"
        wp_enqueue_script('vite-client', $dev_server_url . '/@vite/client', [], null, false);

        // Enqueue the main entry script with type="module"
        wp_enqueue_script(
            'main-script',
            $dev_server_url . '/' . $entry,
            [],
            null,
            false // Ensure the script is loaded in the header
        );
    }

    /**
     * Enqueues the built assets for production.
     */
    private function enqueueProdAssets(string $entry)
    {

        // Use dynamic path for the manifest file
        $manifest_path = plugin_dir_path(__FILE__) . 'dist/.vite/manifest.json';

        $manifest = json_decode(file_get_contents($manifest_path), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Error decoding Vite manifest: ' . json_last_error_msg());
            return; // Exit early if the manifest file cannot be parsed
        }

        // Debugging: Log manifest keys
        if (! isset($manifest)) {
            error_log('Manifest is not set.');
        }

        if (isset($manifest[$entry])) {
            $entry_manifest = $manifest[$entry];
            // Enqueue the main JavaScript file
            if (isset($entry_manifest['file'])) {
                wp_enqueue_script(
                    'main-script',
                    plugins_url('dist/' . $entry_manifest['file'], __FILE__),
                    [],
                    null,
                    true // Load in footer
                );
            }

            // Enqueue associated CSS files
            if (isset($entry_manifest['css'])) {
                foreach ($entry_manifest['css'] as $css_file) {
                    wp_enqueue_style(
                        'main-style',
                        plugin_dir_url(__FILE__) . '../dist/' . $css_file
                    );
                }
            }
        }

        // Enqueue standalone CSS files
        foreach ($manifest as $key => $value) {
            if (isset($value['file']) && pathinfo($value['file'], PATHINFO_EXTENSION) === 'css') {
                wp_enqueue_style(
                    'vite-' . sanitize_title($key), // Prefix to avoid conflicts
                    plugins_url('../dist/' . $value['file'], __FILE__) // Correct path resolution
                );
            }
        }

        // Update enqueue logic to dynamically use Vite manifest
        if (isset($manifest['assets/styles/admin-main.scss']['file'])) {
            $hashed_css_file = $manifest['assets/styles/admin-main.scss']['file'];
            wp_enqueue_style(
                'admin-style',
                plugins_url('dist/' . $hashed_css_file, __FILE__),
                [],
                null
            );
        }
    }
}