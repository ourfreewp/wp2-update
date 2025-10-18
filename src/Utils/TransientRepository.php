<?php

declare(strict_types=1);

namespace WP2\Update\Utils;

defined('ABSPATH') || exit;

/**
 * Repository for managing transient keys.
 */
class TransientRepository
{
    /**
     * Finds all transient keys with a specific prefix.
     *
     * @param string $prefix The prefix to filter transient keys.
     * @return array The list of matching transient keys.
     */
    public function findKeysByPrefix(string $prefix): array
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_' . $prefix) . '%'
        );

        $results = $wpdb->get_col($sql);

        return array_map(function ($option_name) {
            return str_replace('_transient_', '', $option_name);
        }, $results);
    }
}