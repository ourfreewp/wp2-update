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
     * @param array $headers Optional headers to include in the request.
     * @return array|null The decoded JSON response or null on failure.
     */
    public static function get(string $url, array $headers = []): ?array {
        return self::request('GET', $url, ['headers' => $headers]);
    }

    /**
     * Sends a POST request using the WordPress HTTP API.
     *
     * @param string $url The URL to request.
     * @param array $body The body of the POST request.
     * @param array $headers Optional headers to include in the request.
     * @return array|null The decoded JSON response or null on failure.
     */
    public static function post(string $url, array $body, array $headers = []): ?array {
        return self::request('POST', $url, [
            'headers' => $headers,
            'body'    => wp_json_encode($body),
        ]);
    }

    /**
     * Handles the core request logic for the WordPress HTTP API.
     *
     * @param string $method The HTTP method (GET, POST, etc.).
     * @param string $url    The URL to request.
     * @param array  $args   Arguments for wp_remote_request.
     * @return array|null The decoded JSON response or null on failure.
     */
    private static function request(string $method, string $url, array $args): ?array {
        $args['method'] = $method;
        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            Logger::log('ERROR', "HTTP {$method} request failed: " . $response->get_error_message());
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::log('ERROR', 'Failed to decode JSON response: ' . json_last_error_msg());
            return null;
        }

        return is_array($data) ? $data : null;
    }
}
