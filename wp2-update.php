<?php
/**
 * Plugin Name:       WP2 Update
 * Description:       A WordPress plugin that delivers private GitHub theme updates.
 * Version:           0.0.10
 * Author:            Vinny S. Green
 * Text Domain:       wp2-update
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        webmultipliers/wp2-themes
 */

// Ensure the file is not accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ensure Composer autoloader is available.
if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    // Optionally add an admin notice here to inform the user to run `composer install`.
    return;
}
require_once __DIR__ . '/vendor/autoload.php';



// Include the Vite class file
require_once __DIR__ . '/vite.php';



// Don't forget to instantiate the class!
new Vite();


// Check if Action Scheduler is already loaded.
if ( ! class_exists( 'ActionScheduler' ) ) {
    require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
}

// Define core plugin constants.
define( 'WP2_UPDATE_PLUGIN_FILE', __FILE__ );
define( 'WP2_UPDATE_PLUGIN_DIR', __DIR__ );
define( 'WP2_UPDATE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

define( 'WP2_UPDATE_CONSTANTS', [
    'PLUGIN_FILE' => __FILE__,
    'PLUGIN_DIR' => __DIR__,
    'PLUGIN_URL' => plugin_dir_url( __FILE__ ),
    'DEBUG' => defined('WP2_UPDATE_DEBUG') ? WP2_UPDATE_DEBUG : false,
    'GITHUB_API_URL' => defined('WP2_UPDATE_GITHUB_API_URL') ? WP2_UPDATE_GITHUB_API_URL : 'https://api.github.com',
    'LICENSE_URL' => defined('WP2_UPDATE_LICENSE_URL') ? WP2_UPDATE_LICENSE_URL : 'https://www.gnu.org/licenses/gpl-2.0.html',
]);

// Initialize the plugin
add_action('plugins_loaded', function() {
    if (class_exists('WP2\\Update\\Init') && method_exists('WP2\\Update\\Init', 'initialize')) {
        \WP2\Update\Init::initialize();
    } else {
        error_log('WP2 Update: Init class or initialize method does not exist.');
    }
}, 20);

/**
 * Add the manage_options capability to the administrator role on activation.
 */
function wp2_update_activate() {
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->add_cap('manage_options');
    }
}
register_activation_hook(__FILE__, 'wp2_update_activate');

/**
 * Remove the manage_options capability from the administrator role on deactivation.
 */
function wp2_update_deactivate() {
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->remove_cap('manage_options');
    }
}
register_deactivation_hook(__FILE__, 'wp2_update_deactivate');
