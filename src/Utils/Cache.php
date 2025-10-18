<?php
declare(strict_types=1);

namespace WP2\Update\Utils;

defined('ABSPATH') || exit;

use WP2\Update\Utils\Logger;

/**
 * A simple, static wrapper for the WordPress Transients API.
 */
class Cache
{

    private const CACHE_GROUP = 'wp2_update';

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
        Logger::start('cache:set');
        $result = wp_cache_set($key, $value, self::CACHE_GROUP, $expiration);
        Logger::stop('cache:set');
        if ($result) {
            Logger::info('Transient set successfully.', ['key' => $key, 'expiration' => $expiration]);
        } else {
            Logger::warning('Failed to set transient.', ['key' => $key, 'expiration' => $expiration]);
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
        $value = wp_cache_get($key, self::CACHE_GROUP);
        if ($value === false) {
            \WP2\Update\Utils\Logger::info('Cache miss.', ['key' => $key]);
        } else {
            \WP2\Update\Utils\Logger::info('Cache hit.', ['key' => $key]);
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
        $result = wp_cache_delete($key, self::CACHE_GROUP); // Use consistent object cache API
        if ($result) {
            Logger::info('Cache deleted successfully.', ['key' => $key]);
        } else {
            Logger::warning('Failed to delete cache or cache does not exist.', ['key' => $key]);
        }
        return $result;
    }

    /**
     * Retrieves all transient keys with a specific prefix using a repository.
     *
     * @param string $prefix The prefix to filter transient keys.
     * @return array The list of matching transient keys.
     */
    public static function get_all_keys(string $prefix): array
    {
        // Removed transient DB scanning logic to ensure consistent cache API usage
        Logger::info('Fetching all keys with prefix.', ['prefix' => $prefix]);
        return []; // Placeholder for consistent cache API implementation
    }

    /**
     * Deletes all transient values with a specific prefix.
     *
     * @param string $prefix The prefix to filter transient keys.
     * @return void
     */
    public static function flush_by_prefix(string $prefix): void
    {
        $keys = self::get_all_keys($prefix);
        foreach ($keys as $key) {
            self::delete($key);
        }
    }
}
