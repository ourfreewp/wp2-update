<?php
/**
 * Plugin Name:       WP2 Update
 * Description:       A WordPress plugin that delivers private GitHub theme and plugin updates.
 * Version:           0.0.21
 * Author:            Vinny S. Green
 * Text Domain:       wp2-update
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        ourfreewp/wp2-update
 */

// Ensure the file is not accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


// Define core plugin constants.
define( 'WP2_UPDATE_PLUGIN_FILE', __FILE__ );
define( 'WP2_UPDATE_PLUGIN_DIR', __DIR__ );
define( 'WP2_UPDATE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Ensure Composer autoloader is available.
$autoloader = WP2_UPDATE_PLUGIN_DIR . '/vendor/autoload.php';
if ( ! file_exists( $autoloader ) ) {
    // Add a more informative admin notice for the missing autoloader.
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__( 'WP2 Update Error: Composer autoloader not found. Please run `composer install` in the plugin directory.', 'wp2-update' );
        echo '</p></div>';
    });
    return; // Stop execution if the autoloader is missing.
}
require_once $autoloader;

/**
 * The main function to bootstrap the plugin.
 * This is the single entry point that fires when all plugins are loaded.
 */
function wp2_update_run() {
    \WP2\Update\Init::boot();
}
// Hook the single entry point to 'plugins_loaded'.
add_action( 'plugins_loaded', 'wp2_update_run');

// Ensure proper MIME types for CSS and JS files
add_filter('wp_headers', function ($headers) {
    if (isset($_SERVER['REQUEST_URI'])) {
        $path = $_SERVER['REQUEST_URI'];
        if (strpos($path, '.css') !== false) {
            $headers['Content-Type'] = 'text/css; charset=UTF-8';
        } elseif (strpos($path, '.js') !== false) {
            $headers['Content-Type'] = 'application/javascript; charset=UTF-8';
        }
    }
    return $headers;
});

// Dynamically load Vite-built assets.
add_action('admin_enqueue_scripts', function() {
    $manifest_path = WP2_UPDATE_PLUGIN_DIR . '/dist/.vite/manifest.json';

    if (!file_exists($manifest_path)) {
        wp_die(__('Vite manifest not found. Please build the assets.', 'wp2-update'));
    }

    $manifest = json_decode(file_get_contents($manifest_path), true);

    // Enqueue the main JavaScript file.
    if (isset($manifest['admin-main.js'])) {
        wp_enqueue_script(
            'wp2-update-admin-main',
            WP2_UPDATE_PLUGIN_URL . 'dist/' . $manifest['admin-main.js']['file'],
            [],
            null,
            true
        );

        // Localize data for the script.
        wp_add_inline_script('wp2-update-admin-main', 'const wp2UpdateData = ' . json_encode([
            'nonce'   => wp_create_nonce('wp2_update_nonce'),
            'apiRoot' => esc_url_raw(rest_url('wp2-update/v1/')), // Re-added namespace with trailing slash
        ]) . ';', 'before');
    }

    // Enqueue the main CSS file.
    if (isset($manifest['admin-style.css'])) {
        wp_enqueue_style(
            'wp2-update-admin-style',
            WP2_UPDATE_PLUGIN_URL . 'dist/' . $manifest['admin-style.css']['file'],
            [],
            null
        );
    }
});