<?php
declare(strict_types=1);

/**
 * Plugin Name:       WP2 Update
 * Description:       A WordPress plugin that delivers private GitHub theme and plugin updates.
 * Version:           0.0.37
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

// Include Action Scheduler autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Add Content Security Policy header for admin pages
add_action('admin_init', function() {
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self' https://api.github.com; frame-src 'none'; object-src 'none'; base-uri 'self'; form-action 'self';");
});

/**
 * The main function to bootstrap the plugin.
 * This is the single entry point that fires when all plugins are loaded.
 */
function wp2_update_run() {
    \WP2\Update\Init::boot();
}
// Hook the single entry point to 'plugins_loaded'.
add_action( 'plugins_loaded', 'wp2_update_run');

/**
 * Register the activation hook to create database tables using FQCN.
 */
register_activation_hook(__FILE__, [\WP2\Update\Database\Schema::class, 'create_tables']);

// Declare global $wpdb before usage
if (!isset($wpdb)) {
    global $wpdb;
}
