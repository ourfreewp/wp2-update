<?php
/**
 * Plugin Name:       WP2 Update
 * Description:       A WordPress plugin that delivers private GitHub theme and plugin updates.
 * Version:           0.0.30
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
        echo '<div class="wp2-notice wp2-notice--error"><p>';
        echo esc_html__( 'WP2 Update Error: Composer autoloader not found. Please run `composer install` in the plugin directory.', 'wp2-update' );
        echo '</p></div>';
    });
    return; // Stop execution if the autoloader is missing.
}
require_once $autoloader;

// Include Action Scheduler autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Ensure Action Scheduler is loaded
if (class_exists('ActionScheduler')) {
    add_action('init', function () {
        // Example: Schedule a recurring action
        if (!as_next_scheduled_action('wp2_update_example_task')) {
            as_schedule_recurring_action(time(), 3600, 'wp2_update_example_task');
        }
    });

    // Example: Hook into the scheduled action
    add_action('wp2_update_example_task', function () {
        // Task logic here
        error_log('WP2 Update example task executed.');
    });
} else {
    error_log('Action Scheduler is not available.');
}

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
 * Create the database table for logging on plugin activation.
 */
function wp2_update_create_log_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wp2_update_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        timestamp DATETIME NOT NULL,
        level VARCHAR(20) NOT NULL,
        message TEXT NOT NULL,
        context TEXT NULL,
        PRIMARY KEY (id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
// Register the activation hook to create the log table.
register_activation_hook( __FILE__, 'wp2_update_create_log_table' );

// Declare global $wpdb before usage
if (!isset($wpdb)) {
    global $wpdb;
}
