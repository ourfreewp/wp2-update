<?php

namespace WP2\Update\Utils;

/**
 * A simple wrapper around WordPress's HTTP API for making remote requests.
 */
final class HttpClient {
    /**
     * Sends a GET request using the WordPress HTTP API.
     *
     * @param string $url The URL to request.
     * @param array $args Optional arguments for the request.
     * @return array|string|null The decoded JSON response, raw body, or null on failure.
     */
    public static function get(string $url, array $args = []): mixed {
        return self::request('GET', $url, $args);
    }

    /**
     * Sends a POST request using the WordPress HTTP API.
     *
     * @param string $url The URL to request.
     * @param array $args Arguments for the request, including body and headers.
     * @return array|string|null The decoded JSON response, raw body, or null on failure.
     */
    public static function post(string $url, array $args = []): mixed {
        return self::request('POST', $url, $args);
    }

    /**
     * Handles the core request logic for the WordPress HTTP API.
     *
     * @param string $method The HTTP method (GET, POST, etc.).
     * @param string $url    The URL to request.
     * @param array  $args   Arguments for wp_remote_request.
     * @return array|string|null The decoded JSON response, raw body, or null on failure.
     */
    private static function request(string $method, string $url, array $args): mixed {
        $args['method'] = $method;
        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            Logger::log('ERROR', "HTTP {$method} request failed: " . $response->get_error_message());
            return null;
        }

        $body = wp_remote_retrieve_body($response);

        // Check if the response is JSON and decode it, otherwise return raw body.
        $data = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $data;
        }

        return $body; // Return raw body if not JSON.
    }
}
