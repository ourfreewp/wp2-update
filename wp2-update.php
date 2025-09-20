<?php
/**
 * Plugin Name:       WP2 Update
 * Description:       A WordPress plugin that delivers private GitHub theme updates.
 * Version:           0.0.1
 * Author:            Vinny S. Green
 * Text Domain:       wp2-update
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        webmultipliers/wp2-themes
 */

namespace WP2\Update;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ensure Composer autoloader is available.
if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    // Optionally add an admin notice here to inform the user to run `composer install`.
    return;
}
require_once __DIR__ . '/vendor/autoload.php';

// Check if Action Scheduler is already loaded.
if ( ! class_exists( 'ActionScheduler' ) ) {
    require_once __DIR__ . '/vendor/action-scheduler/action-scheduler.php';
}

// Define core plugin constants.
define( 'WP2_UPDATE_PLUGIN_FILE', __FILE__ );
define( 'WP2_UPDATE_PLUGIN_DIR', __DIR__ );
define( 'WP2_UPDATE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Define constants with default fallbacks.
if ( ! defined( 'WP2_UPDATE_DEBUG' ) ) {
    define( 'WP2_UPDATE_DEBUG', false );
}
if ( ! defined( 'WP2_UPDATE_GITHUB_API_URL' ) ) {
    define( 'WP2_UPDATE_GITHUB_API_URL', 'https://api.github.com' );
}

/**
 * Initializes the plugin and gets all the components running.
 */
function run_wp2_update() {
    require_once __DIR__ . '/src/Init.php';
    \WP2\Update\Init::initialize();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\run_wp2_update' );


require_once plugin_dir_path(__FILE__) . 'vite.php';

if ( class_exists( 'Vite' ) ) {
  new \Vite();
}
