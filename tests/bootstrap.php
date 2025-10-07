<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Dynamically load WordPress core files using @wordpress/env.
if ( ! defined( 'ABSPATH' ) ) {
    $wp_env_path = getenv('WP_ENV_PATH') ?: '/tmp/wordpress'; // Default to /tmp/wordpress if WP_ENV_PATH is not set.
    require_once $wp_env_path . '/wp-load.php';
}

// Include Pest's testing functions.
require_once __DIR__ . '/Pest.php';

// Mock WordPress functions for unit tests.
function get_transient($key) {
    return null;
}

function set_transient($key, $value, $expiration) {
    // Do nothing.
}

function delete_transient($key) {
    // Do nothing.
}

function wp_get_theme($slug) {
    return (object) ['get' => fn($key) => '1.0.0'];
}

function get_plugins() {
    return [
        'example-plugin/example-plugin.php' => ['Version' => '1.0.0']
    ];
}

function wp_update_themes() {
    // Simulate theme update.
}

// Setup and teardown for Brain Monkey.
class BrainMonkeyTestCase extends \PHPUnit\Framework\TestCase {
    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }
}
