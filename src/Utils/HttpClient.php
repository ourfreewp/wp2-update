<?php

namespace WP2\Update\Utils;

use WP_Error;

class HttpClient {
    /**
     * Perform a GET request.
     *
     * @param string $url The URL to request.
     * @param array $args Optional. Additional arguments for the request.
     * @return array|null The response body as an array, or null on failure.
     */
    public static function get(string $url, array $args = []): ?array {
        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            Logger::log('ERROR', 'HTTP GET request failed: ' . $response->get_error_message());
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::log('ERROR', 'Failed to decode JSON response: ' . json_last_error_msg());
            return null;
        }

        return $data;
    }

    /**
     * Perform a POST request.
     *
     * @param string $url The URL to request.
     * @param array $args Optional. Additional arguments for the request.
     * @return array|null The response body as an array, or null on failure.
     */
    public static function post(string $url, array $args = []): ?array {
        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            Logger::log('ERROR', 'HTTP POST request failed: ' . $response->get_error_message());
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::log('ERROR', 'Failed to decode JSON response: ' . json_last_error_msg());
            return null;
        }

        return $data;
    }
}