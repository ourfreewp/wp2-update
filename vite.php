<?php
class Vite
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        // Use 'admin_enqueue_scripts' for admin-specific assets
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
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
     * Always returns false to ensure production assets are used.
     */
    private function isViteDevServerRunning(): bool
    {
        return false; // Always use production assets
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
        $manifest_path = plugin_dir_path(__FILE__) . 'dist/.vite/manifest.json';

        // Debugging: Log the manifest file path
        error_log('Manifest path: ' . $manifest_path);

        if (!file_exists($manifest_path)) {
            error_log('Manifest file does not exist.');
            return;
        }

        $manifest = json_decode(file_get_contents($manifest_path), true);

        // Debugging: Log the manifest content
        error_log('Manifest content: ' . print_r($manifest, true));

        if (isset($manifest[$entry])) {
            $entry_manifest = $manifest[$entry];

            // Debugging: Log the entry manifest
            error_log('Entry manifest: ' . print_r($entry_manifest, true));

            // Enqueue the main JavaScript file
            if (isset($entry_manifest['file'])) {
                error_log('Enqueuing JS file: ' . $entry_manifest['file']);
                wp_enqueue_script(
                    'main-script',
                    plugin_dir_url(__FILE__) . 'dist/' . $entry_manifest['file'],
                    [], // Add dependencies like 'jquery' if needed
                    null,
                    true // Load in footer
                );
            }

            // Enqueue associated CSS files
            if (isset($entry_manifest['css'])) {
                foreach ($entry_manifest['css'] as $css_file) {
                    error_log('Enqueuing CSS file: ' . $css_file);
                    wp_enqueue_style(
                        'main-style',
                        plugin_dir_url(__FILE__) . 'dist/' . $css_file
                    );
                }
            }
        }

        // Enqueue standalone CSS files
        foreach ($manifest as $key => $value) {
            if (isset($value['file']) && pathinfo($value['file'], PATHINFO_EXTENSION) === 'css') {
                error_log('Enqueuing standalone CSS file: ' . $value['file']);
                wp_enqueue_style(
                    'vite-' . sanitize_title($key), // Prefix to avoid conflicts
                    plugins_url('dist/' . $value['file'], __FILE__) // Correct path resolution
                );
            }
        }
    }
}
