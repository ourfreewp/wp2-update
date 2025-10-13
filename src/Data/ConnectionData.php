<?php

namespace WP2\Update\Data;

use WP2\Update\Config;
use WP2\Update\Utils\Logger;

/**
 * Handles all CRUD operations for GitHub App connection data,
 * abstracting the underlying WordPress options storage mechanism.
 */
final class ConnectionData
{
    /**
     * In-memory cache of connection records to reduce database reads.
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $cache = null;

    /**
     * Loads connection data from the database into the cache upon instantiation.
     */
    public function __construct()
    {
        $this->load();
    }

    /**
     * Retrieve all connection records.
     * @return array<int, array<string, mixed>> Indexed array of app data.
     */
    public function all(): array
    {
        return array_values($this->cache);
    }

    /**
     * Find a connection record by its unique identifier.
     * @param string $id The unique identifier for the connection.
     * @return array<string, mixed>|null The connection data or null if not found.
     */
    public function find(string $id): ?array
    {
        return $this->cache[$id] ?? null;
    }

    /**
     * Find connection records where a specific field matches a given value.
     * @param string $field The field name to search by (e.g., 'installation_id').
     * @param mixed $value The value to match.
     * @return array<int, array<string, mixed>> A list of matching connection records.
     */
    public function find_by_field(string $field, $value): array
    {
        return array_values(array_filter($this->cache, function ($connection) use ($field, $value) {
            return isset($connection[$field]) && $connection[$field] == $value;
        }));
    }

    /**
     * Save or update a connection record.
     * @param array<string, mixed> $app_data The data for the connection.
     * @return array<string, mixed> The saved connection data, including its `id`.
     */
    public function save(array $app_data): array
    {
        // Ensure an ID exists, generating one if it's a new record.
        $id = $app_data['id'] ?? wp_generate_uuid4();
        $app_data['id'] = $id;

        // Set timestamps
        if (!isset($app_data['created_at'])) {
            $app_data['created_at'] = current_time('mysql', true);
        }
        $app_data['updated_at'] = current_time('mysql', true);

        $this->cache[$id] = $app_data;
        $this->persist();

        return $this->cache[$id];
    }

    /**
     * Remove a connection record by its unique identifier.
     * @param string $id The identifier for the connection to delete.
     */
    public function delete(string $id): void
    {
        if (isset($this->cache[$id])) {
            unset($this->cache[$id]);
            $this->persist();
        }
    }

    /**
     * Remove all stored connection records.
     */
    public function delete_all(): void
    {
        $this->cache = [];
        $this->persist();
    }

    /**
     * Writes the in-memory cache to the WordPress options table.
     */
    private function persist(): void
    {
        update_option(Config::OPTION_APPS, $this->cache, 'no'); // Do not autoload
    }

    /**
     * Loads all connection records from the WordPress options table into the cache.
     */
    private function load(): void
    {
        $apps = get_option(Config::OPTION_APPS, []);
        if (!is_array($apps)) {
            Logger::log('ERROR', 'Invalid connection data in database. Expected array, got ' . gettype($apps));
            $this->cache = [];
            return;
        }

        // Normalize data to ensure it's always indexed by a valid ID.
        $normalized = [];
        foreach ($apps as $key => $app) {
            if (is_array($app) && !empty($app['id'])) {
                $normalized[$app['id']] = $app;
            }
        }
        $this->cache = $normalized;
    }

    /**
     * Finds the first active (installed) app.
     * This is useful for contexts where a single connection is assumed.
     * @return array|null
     */
    public function find_active_app(): ?array
    {
        foreach ($this->cache as $app) {
            if (($app['status'] ?? '') === 'installed') {
                return $app;
            }
        }
        return null;
    }
}
