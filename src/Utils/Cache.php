<?php

namespace WP2\Update\Utils;

/**
 * Utility class for managing WordPress transients.
 */
class Cache
{
    /**
     * Sets a transient with a given key and value.
     *
     * @param string $key The transient key.
     * @param mixed $value The value to store.
     * @param int $expiration Expiration time in seconds.
     * @return bool True if the transient was set, false otherwise.
     */
    public static function set(string $key, $value, int $expiration = 0): bool
    {
        return set_transient($key, $value, $expiration);
    }

    /**
     * Retrieves a transient by its key.
     *
     * @param string $key The transient key.
     * @return mixed The value of the transient, or false if not found.
     */
    public static function get(string $key)
    {
        return get_transient($key);
    }

    /**
     * Deletes a transient by its key.
     *
     * @param string $key The transient key.
     * @return bool True if the transient was deleted, false otherwise.
     */
    public static function delete(string $key): bool
    {
        return delete_transient($key);
    }

    /**
     * Clears all transients related to the plugin.
     *
     * @param string $prefix The prefix for plugin-related transients.
     */
    public static function clear_all(string $prefix): void
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like("_transient_{$prefix}") . '%'
        );

        $results = $wpdb->get_col($sql);

        foreach ($results as $option_name) {
            $transient_key = str_replace('_transient_', '', $option_name);
            delete_transient($transient_key);
        }
    }
}