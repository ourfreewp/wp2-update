<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Ensure Brain Monkey is loaded globally for all tests
use Brain\Monkey;

// Setup Brain Monkey before each test
beforeEach(function () {
    Monkey\setUp();
});

// Tear down Brain Monkey after each test
afterEach(function () {
    Monkey\tearDown();
});

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

function wp_update_plugins() {
    // Simulate plugin update.
}

function current_time($type) {
    return time();
}

function wp_die($message) {
    throw new Exception($message);
}

function add_filter($hook, $callback) {
    // Simulate adding a filter.
}

function add_action($hook, $callback) {
    // Simulate adding an action.
}
