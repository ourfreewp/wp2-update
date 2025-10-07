<?php
/**
 * Plugin Name:       WP2 Update
 * Description:       A WordPress plugin that delivers private GitHub theme updates.
 * Version:           0.0.12
 * Author:            Vinny S. Green
 * Text Domain:       wp2-update
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.3
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
    wp_die( 'Composer autoloader not found. Please run `composer install`.' );
}
require_once $autoloader;

// Check if Action Scheduler is already loaded.
if ( ! class_exists( 'ActionScheduler' ) ) {
    require_once WP2_UPDATE_PLUGIN_DIR . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
}

/**
 * The main function to bootstrap the plugin.
 * This is the single entry point that fires when all plugins are loaded.
 */
function wp2_update_run() {
    // The autoloader makes the WP2\Update\Init class available.
    if ( class_exists( 'WP2\\Update\\Init' ) ) {
        // Directly call the static method that contains all setup logic.
        \WP2\Update\Init::boot();
    } else {
        wp_die( 'Critical Error: WP2\\Update\\Init class not found. Check your Composer PSR-4 autoloader configuration.' );
    }
}
// Hook the single entry point to 'plugins_loaded'.
add_action( 'plugins_loaded', 'wp2_update_run' );

// Register activation hook.
register_activation_hook( WP2_UPDATE_PLUGIN_FILE, function() {
    $admin_role = get_role( 'administrator' );
    if ( $admin_role ) {
        $admin_role->add_cap( 'manage_options' );
    }
} );