<?php

namespace WP2\Update\Utils;

/**
 * A simple wrapper around WordPress's HTTP API for making remote requests.
 */
final class HttpClient {
    /**
     * Sends a GET request.
     * @return array|string|null The decoded JSON response, raw body, or null on failure.
     */
    public static function get(string $url, array $args = []) {
        return self::request('GET', $url, $args);
    }

    /**
     * Sends a POST request.
     * @return array|string|null The decoded JSON response, raw body, or null on failure.
     */
    public static function post(string $url, array $args = []) {
        return self::request('POST', $url, $args);
    }

    /**
     * Handles the core request logic using wp_remote_request.
     * @return array|string|null The decoded JSON response, raw body, or null on failure.
     */
    private static function request(string $method, string $url, array $args) {
        $args['method'] = $method;

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            Logger::log('ERROR', "HTTP {$method} request to {$url} failed: " . $response->get_error_message());
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Return decoded JSON if possible, otherwise return the raw body.
        return (json_last_error() === JSON_ERROR_NONE) ? $data : $body;
    }
}
