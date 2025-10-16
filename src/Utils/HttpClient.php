<?php
declare(strict_types=1);

namespace WP2\Update\Utils;

use WP2\Update\Utils\Logger;

/**
 * A simple wrapper around WordPress's HTTP API for making remote requests.
 */
final class HttpClient {

    /**
     * Sends a GET request.
     * @return array|string|null The decoded JSON response, raw body, or null on failure.
     */
    public static function get(string $url, array $args = []) {
        Logger::info('Sending GET request.', ['url' => $url, 'args' => $args]);
        $response = self::request('GET', $url, $args);
        if ($response === null) {
            Logger::error('GET request failed.', ['url' => $url]);
        } else {
            Logger::info('GET request succeeded.', ['url' => $url, 'response' => $response]);
        }
        return $response;
    }

    /**
     * Sends a POST request.
     * @return array|string|null The decoded JSON response, raw body, or null on failure.
     */
    public static function post(string $url, array $args = []) {
        Logger::info('Sending POST request.', ['url' => $url, 'args' => $args]);
        $response = self::request('POST', $url, $args);
        if ($response === null) {
            Logger::error('POST request failed.', ['url' => $url]);
        } else {
            Logger::info('POST request succeeded.', ['url' => $url, 'response' => $response]);
        }
        return $response;
    }

    /**
     * Handles the core request logic using wp_remote_request.
     * @return array|string|null The decoded JSON response, raw body, or null on failure.
     */
    private static function request(string $method, string $url, array $args) {
        Logger::info('Making HTTP request.', ['method' => $method, 'url' => $url, 'args' => $args]);
        $args['method'] = $method;

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            Logger::error('HTTP request error.', ['method' => $method, 'url' => $url, 'error' => $response->get_error_message()]);
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            Logger::info('HTTP request returned JSON response.', ['method' => $method, 'url' => $url, 'response' => $data]);
            return $data;
        } else {
            Logger::warning('HTTP request returned non-JSON response.', ['method' => $method, 'url' => $url, 'response' => $body]);
            return $body;
        }
    }
}
