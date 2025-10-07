<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/brain/monkey/inc/api.php';

// Dynamically load WordPress core files using @wordpress/env.
if ( ! defined( 'ABSPATH' ) ) {
    $wp_env_path = getenv('WP_ENV_PATH') ?: '/tmp/wordpress'; // Default to /tmp/wordpress if WP_ENV_PATH is not set.
    if ( file_exists( $wp_env_path . '/wp-load.php' ) ) {
        require_once $wp_env_path . '/wp-load.php';
    } else {
        define( 'ABSPATH', __DIR__ . '/' ); // Mock ABSPATH for tests.
    }
}

// Enable debug logging for tests.
define('WP2_UPDATE_DEBUG', true);

// Include Pest's testing functions.
require_once __DIR__ . '/Pest.php';

// Initialize Brain Monkey for mocking WordPress functions.
Brain\Monkey\setUp();

// Register shutdown function to tear down Brain Monkey.
register_shutdown_function(function () {
    Brain\Monkey\tearDown();
});
