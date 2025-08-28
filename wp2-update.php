<?php
/**
 * Plugin Name:       WP2 Update
 * Description:       A custom MU-plugin to handle updates for private themes and plugins from GitHub.
 * Version:           1.0.0
 * Author:            Web Mutlipliers
 * Text Domain:       wp2-update
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Debug flag for logging and admin UI
if ( ! defined( 'WP2_UPDATE_DEBUG' ) ) {
    define( 'WP2_UPDATE_DEBUG', false );
}


// --- Environment/Config Validation ---
add_action('admin_notices', function() {
    if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
        return;
    }
    $errors = [];
    if (!defined('WP2_GITHUB_APP_ID') || !is_numeric(WP2_GITHUB_APP_ID)) {
        $errors[] = __('WP2 Update: GitHub App ID is not configured correctly in wp-config.php.', 'wp2-update');
    }
    if (!defined('WP2_GITHUB_INSTALLATION_ID') || !is_numeric(WP2_GITHUB_INSTALLATION_ID)) {
        $errors[] = __('WP2 Update: GitHub Installation ID is not configured correctly in wp-config.php.', 'wp2-update');
    }
    if (!defined('WP2_GITHUB_PRIVATE_KEY_PATH') || !file_exists(WP2_GITHUB_PRIVATE_KEY_PATH)) {
        $errors[] = __('WP2 Update: GitHub Private Key path is invalid or file does not exist.', 'wp2-update');
    }
    if (!extension_loaded('openssl')) {
        $errors[] = __('WP2 Update: The PHP OpenSSL extension is required for GitHub App authentication.', 'wp2-update');
    }
    if (!empty($errors)) {
        echo '<div class="notice notice-error"><p><strong>WP2 Updater Error</strong></p><ul style="list-style: disc; margin-left: 20px;">';
        foreach ($errors as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        echo '</ul></div>';
    }
});

// --- Load Core Files ---
$autoloader = __DIR__ . '/wp2-update/vendor/autoload.php';
$debug = [];
$debug[] = 'Autoloader path: ' . $autoloader;
$debug[] = 'Autoloader exists: ' . (file_exists($autoloader) ? 'yes' : 'no');
if (file_exists($autoloader)) {
    require_once $autoloader;
    $debug[] = 'Autoloader required.';
} else {
    add_action('admin_notices', function() use ($debug) {
        echo '<div class="notice notice-error"><p><strong>WP2 Updater Error:</strong> Composer autoloader not found. Please run <code>composer install</code>.</p><pre>' . implode("\n", $debug) . '</pre></div>';
    });
    return;
}
$debug[] = 'Init class exists: ' . (class_exists('WP2\Update\Init') ? 'yes' : 'no');
// The core update service orchestrator and dashboard
if (class_exists('WP2\Update\Init')) {
    new WP2\Update\Init();
} else {
    add_action('admin_notices', function() use ($debug) {
        echo '<div class="notice notice-error"><p><strong>WP2 Updater Error:</strong> Could not find Init class. Please check your autoloader and src/init.php.</p><pre>' . implode("\n", $debug) . '</pre></div>';
    });
}
new \WP2\Update\Helpers\DashboardAdmin();