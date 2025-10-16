<?php
declare(strict_types=1);

namespace WP2\Update\Utils;
use WP2\Update\Utils\Logger;

/**
 * A simple, static wrapper for the WordPress Transients API.
 */
class Cache
{

    /**
     * Sets a transient (cache) value.
     *
     * @param string $key The transient key. Must be 172 characters or fewer.
     * @param mixed $value The value to store.
     * @param int $expiration Time until expiration in seconds. 0 means no expiration.
     * @return bool True if the value was set, false otherwise.
     */
    public static function set(string $key, $value, int $expiration = 0): bool
    {
        $result = set_transient($key, $value, $expiration);
        if ($result) {
            Logger::info('Transient set successfully.', ['key' => $key, 'expiration' => $expiration]);
        } else {
            Logger::error('Failed to set transient.', ['key' => $key]);
        }
        return $result;
    }

    /**
     * Retrieves a transient value.
     *
     * @param string $key The transient key.
     * @return mixed The value of the transient, or false if it does not exist or has expired.
     */
    public static function get(string $key)
    {
        $value = get_transient($key);
        if ($value !== false) {
            Logger::info('Transient retrieved successfully.', ['key' => $key]);
        } else {
            Logger::warning('Transient not found or expired.', ['key' => $key]);
        }
        return $value;
    }

    /**
     * Deletes a transient value.
     *
     * @param string $key The transient key.
     * @return bool True if the transient was deleted, false otherwise.
     */
    public static function delete(string $key): bool
    {
        $result = delete_transient($key);
        if ($result) {
            Logger::info('Transient deleted successfully.', ['key' => $key]);
        } else {
            Logger::warning('Failed to delete transient or transient does not exist.', ['key' => $key]);
        }
        return $result;
    }
}
