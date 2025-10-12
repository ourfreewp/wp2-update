<?php

namespace WP2\Update\Core;

use WP2\Update\Config;
use WP2\Update\Utils\Logger;

/**
 * Persists GitHub App definitions.
 */
class AppRepository
{
    /**
     * Cached collection of apps.
     *
     * @var array<string,array>
     */
    private array $cache = [];

    public function __construct()
    {
        $this->cache = $this->load();
    }

    /**
     * Retrieve all apps.
     *
     * @return array<int,array>
     */
    public function all(): array
    {
        return array_values($this->cache);
    }

    /**
     * Find an app by internal id.
     */
    public function find(string $id): ?array
    {
        return $this->cache[$id] ?? null;
    }

    /**
     * Persist an app definition.
     *
     * @param array $app
     * @return array The saved app.
     */
    public function save(array $app): array
    {
        $apps = $this->cache;
        $id   = isset($app['id']) && $app['id'] !== '' ? (string) $app['id'] : wp_generate_uuid4();

        $app['id']         = $id;
        $app['created_at'] = $app['created_at'] ?? current_time('mysql');
        $app['updated_at'] = current_time('mysql');

        $apps[$id] = $app;

        $this->persist($apps);

        return $apps[$id];
    }

    /**
     * Remove an app definition.
     */
    public function delete(string $id): void
    {
        if (!isset($this->cache[$id])) {
            return;
        }

        $apps = $this->cache;
        unset($apps[$id]);
        $this->persist($apps);
    }

    /**
     * Remove all stored apps.
     */
    public function delete_all(): void
    {
        $this->persist([]);
    }

    /**
     * Persist the provided collection and refresh cache.
     *
     * @param array<string,array> $apps
     */
    private function persist(array $apps): void
    {
        $this->cache = $apps;
        update_option(Config::OPTION_APPS, $apps, false);
    }

    /**
     * Load all apps from storage.
     *
     * @return array<string,array>
     */
    private function load(): array
    {
        $apps = get_option(Config::OPTION_APPS, []);
        if (!is_array($apps)) {
            Logger::log('ERROR', 'Invalid apps data retrieved from storage. Expected array, got: ' . gettype($apps));
            return [];
        }

        // Ensure entries are indexed by id.
        $normalized = [];
        foreach ($apps as $key => $app) {
            if (!is_array($app)) {
                Logger::log('WARNING', 'Skipping invalid app entry. Expected array, got: ' . gettype($app));
                continue;
            }
            $id = isset($app['id']) && is_string($app['id']) ? $app['id'] : (is_string($key) ? $key : wp_generate_uuid4());
            $app['id'] = $id;
            $normalized[$id] = $app;
        }

        if ($normalized !== $apps) {
            update_option(Config::OPTION_APPS, $normalized, false);
        }

        Logger::log('INFO', 'Loaded apps from storage: ' . count($normalized) . ' apps found.');
        return $normalized;
    }

    /**
     * Retrieve apps by a specific field value.
     *
     * @param string $field The field name to filter by.
     * @param mixed $value The value to match.
     * @return array<int,array> Matching apps.
     */
    public function find_by_field(string $field, $value): array
    {
        return array_values(array_filter($this->cache, function ($app) use ($field, $value) {
            return isset($app[$field]) && $app[$field] === $value;
        }));
    }

    /**
     * Retrieve all apps.
     *
     * @return array<int,array> The list of all apps.
     */
    public function find_all(): array
    {
        return $this->all();
    }

    /**
     * Updates the managed repositories for a given app UID.
     *
     * @param string $appUid The app UID.
     * @param array $repositories The list of repositories to associate with the app.
     * @return void
     */
    public function update_managed_repositories(string $appUid, array $repositories): void
    {
        if (!isset($this->cache[$appUid])) {
            Logger::log('WARNING', 'App UID not found: ' . $appUid);
            return;
        }

        $this->cache[$appUid]['repositories'] = $repositories;
        $this->persist($this->cache);

        Logger::log('INFO', 'Managed repositories updated for app UID: ' . $appUid);
    }
}
