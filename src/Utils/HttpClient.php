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
    public static function get(string $url, array $headers = []): array|string|null {
        $timer_name = 'http:get_request';
        Logger::start($timer_name);
        try {
            $args = [
                'headers' => $headers,
                'timeout' => 15,
            ];
            $result = self::request('GET', $url, $args);
            Logger::stop($timer_name);
            \WP2\Update\Utils\Logger::info('HTTP GET request successful.', ['url' => $url]);
            return $result;
        } catch (\Exception $e) {
            Logger::error('HTTP GET request failed.', ['url' => $url, 'error' => $e->getMessage()]);
            throw $e;
        } finally {
            Logger::stop($timer_name);
        }
    }

    /**
     * Sends a POST request.
     * @return array|string|null The decoded JSON response, raw body, or null on failure.
     */
    public static function post(string $url, array $data, array $headers = []): array|string|null {
        $timer_name = 'http:post_request';
        Logger::start($timer_name);
        try {
            $args = [
                'headers' => $headers,
                'body' => json_encode($data),
                'timeout' => 15,
            ];
            $result = self::request('POST', $url, $args);
            Logger::stop($timer_name);
            \WP2\Update\Utils\Logger::info('HTTP POST request successful.', ['url' => $url]);
            return $result;
        } catch (\Exception $e) {
            Logger::error('HTTP POST request failed.', ['url' => $url, 'error' => $e->getMessage()]);
            throw $e;
        } finally {
            Logger::stop($timer_name);
        }
    }

    /**
     * Handles the core request logic using wp_remote_request.
     * @return array|string|null The decoded JSON response, raw body, or null on failure.
     */
    private static function request(string $method, string $url, array $args): array|string|null {
        $timer_name = 'http:' . strtolower($method);
        Logger::start($timer_name);
        Logger::info('Making HTTP request.', ['method' => $method, 'url' => $url, 'args' => $args]);

        \WP2\Update\Utils\Logger::start( 'update:http:request' );

        $args['method'] = $method;

        $response = wp_remote_request($url, $args);
        \WP2\Update\Utils\Logger::stop( 'update:http:request' );
        \WP2\Update\Utils\Logger::debug( 'HTTP request completed', [ 'url' => $url, 'method' => $method, 'status_code' => $response['response']['code'] ?? null ] );

        Logger::stop($timer_name);

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
