<?php

namespace WP2\Update\Utils;

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
        return set_transient($key, $value, $expiration);
    }

    /**
     * Retrieves a transient value.
     *
     * @param string $key The transient key.
     * @return mixed The value of the transient, or false if it does not exist or has expired.
     */
    public static function get(string $key)
    {
        return get_transient($key);
    }

    /**
     * Deletes a transient value.
     *
     * @param string $key The transient key.
     * @return bool True if the transient was deleted, false otherwise.
     */
    public static function delete(string $key): bool
    {
        return delete_transient($key);
    }
}
