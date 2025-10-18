<?php

namespace WP2\Update\Data;

defined('ABSPATH') || exit;

use WP2\Update\Config;
use WP2\Update\Data\DTO\AppDTO;
use WP2\Update\Utils\Logger;
use WP2\Update\Utils\Encryption;

/**
 * Class AppData
 *
 * Handles all CRUD operations for GitHub App connection data,
 * abstracting the underlying WordPress options storage mechanism.
 */
final class AppData
{
    /**
     * Encryption utility for sensitive fields.
     *
     * @var Encryption
     */
    private Encryption $encryption;

    public function __construct(?Encryption $encryption = null)
    {
        $this->encryption = $encryption ?? new Encryption();
    }
    /**
     * In-memory cache of connection records to reduce database reads.
     *
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $cache = null;

    /**
     * Determines whether to use site-level options for multisite compatibility.
     *
     * @return callable The appropriate WordPress option function.
     */
    private function get_option_function(): callable
    {
        return is_multisite() ? 'get_site_option' : 'get_option';
    }

    /**
     * Loads connection data from the database into the cache.
     *
     * @param string|null $id The ID of the app to load, or null to load all apps.
     * @return void
     */
    private function load(string $id = null): void
    {
        $get_option = $this->get_option_function();

        if ($this->cache === null) {
            $raw = $get_option(Config::OPTION_APPS, []);
            // Decrypt webhook_secret and private_key if present
            foreach ($raw as $appId => $data) {
                if (!empty($data['webhook_secret'])) {
                    $decrypted = $this->encryption->decrypt($data['webhook_secret']);
                    if ($decrypted !== false) {
                        $data['webhook_secret'] = $decrypted;
                    }
                }
                if (!empty($data['private_key'])) {
                    $decryptedKey = $this->encryption->decrypt($data['private_key']);
                    if ($decryptedKey !== false) {
                        $data['private_key'] = $decryptedKey;
                    }
                }
                if (empty($data['private_key']) && !empty($data['metadata']['private_key'])) {
                    $fallbackKey = $this->encryption->decrypt($data['metadata']['private_key']);
                    if ($fallbackKey !== false) {
                        $data['private_key'] = $fallbackKey;
                        unset($data['metadata']['private_key']);
                    }
                }
                $raw[$appId] = $data;
            }
            $this->cache = $raw;
        }
        // Ensure all app data is loaded into the cache
        if ($id !== null) {
            $this->cache[$id] = $this->cache[$id] ?? null;
        }
    }

    /**
     * Retrieve all connection records.
     *
     * @return AppDTO[] Indexed array of AppDTO objects.
     */
    public function get_all(): array
    {
        $this->load();
        $cache = is_array($this->cache) ? $this->cache : []; // Ensure $this->cache is always an array

        return array_values(array_filter(array_map(function ($data) {
            if (empty($data['id'])) { // Skip records without an ID
                error_log('Invalid app data: Missing ID. Data: ' . json_encode($data));
                return null;
            }
            try {
                return AppDTO::fromArray($data);
            } catch (\InvalidArgumentException $e) {
                error_log('Invalid app data: ' . $e->getMessage() . '. Data: ' . json_encode($data));
                return null; // Skip invalid entries
            }
        }, array_values($cache))));
    }

    /**
     * Find a connection record by its unique identifier.
     *
     * @param string $id The unique identifier for the connection.
     * @return AppDTO|null The connection data as an AppDTO or null if not found.
     */
    public function find(string $id): ?AppDTO
    {
        $this->load($id);
        return isset($this->cache[$id]) ? AppDTO::fromArray($this->cache[$id]) : null;
    }

    /**
     * Find connection records where a specific field matches a given value.
     *
     * @param string $field The field name to search by (e.g., 'installation_id').
     * @param mixed $value The value to match.
     * @return AppDTO[] A list of matching connection records as AppDTO objects.
     */
    public function find_by_field(string $field, $value): array
    {
        $this->load();
        return array_values(array_filter(array_map(function ($connection) use ($field, $value) {
            if (isset($connection[$field]) && $connection[$field] == $value) {
                return AppDTO::fromArray($connection);
            }
            return null;
        }, $this->cache)));
    }

    /**
     * Save or update a connection record.
     *
     * @param AppDTO $appDTO The AppDTO object to save.
     * @return AppDTO The saved AppDTO object.
     */
    public function save(AppDTO $appDTO): AppDTO
    {
        $arr = $appDTO->toArray();
        unset($arr['metadata']['private_key']);
        // Encrypt webhook_secret before saving
        if (!empty($arr['webhook_secret'])) {
            $encrypted = $this->encryption->encrypt($arr['webhook_secret']);
            if ($encrypted !== false) {
                $arr['webhook_secret'] = $encrypted;
            }
        }
        // Updated AppData to handle private_key encryption
        if (!empty($arr['private_key'])) {
            $encryptedKey = $this->encryption->encrypt($arr['private_key']);
            if ($encryptedKey !== false) {
                $arr['private_key'] = $encryptedKey;
            }
        }
        $this->cache[$appDTO->id] = $arr;
        $this->persist();
        return $appDTO;
    }

    /**
     * Remove a connection record by its unique identifier.
     *
     * @param string $id The identifier for the connection to delete.
     * @return void
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
     *
     * @return void
     */
    public function delete_all(): void
    {
        $this->cache = [];
        $this->persist();
    }

    /**
     * Writes the in-memory cache to the WordPress options.
     *
     * @return void
     */
    private function persist(): void
    {
        // Encrypt webhook_secret for all apps before persisting
        $to_save = [];
        foreach ($this->cache as $id => $data) {
            unset($data['metadata']['private_key']);
            if (!empty($data['webhook_secret'])) {
                $encrypted = $this->encryption->encrypt($data['webhook_secret']);
                if ($encrypted !== false) {
                    $data['webhook_secret'] = $encrypted;
                }
            }
            if (!empty($data['private_key'])) {
                $encryptedKey = $this->encryption->encrypt($data['private_key']);
                if ($encryptedKey !== false) {
                    $data['private_key'] = $encryptedKey;
                }
            }
            $to_save[$id] = $data;
        }
        $update_option = is_multisite() ? 'update_site_option' : 'update_option';
        $update_option(Config::OPTION_APPS, $to_save, false);
    }

    /**
     * Finds the first active (installed) app.
     * This is useful for contexts where a single connection is assumed.
     *
     * @return array|null The first active app data or null if none found.
     */
    public function find_active_app(): ?array
    {
        $this->load(); // Ensure the cache is initialized before accessing it
        foreach ($this->cache as $app) {
            if (($app['status'] ?? '') === 'installed') {
                return $app;
            }
        }
        return null;
    }

    /**
     * Get all GitHub apps from the cache.
     *
     * @return array
     */
    public function getApps(): array
    {
        if (empty($this->cache)) {
            $this->load();
        }
        return $this->cache;
    }

    /**
     * Resolve the app ID.
     *
     * @param string|null $app_id
     * @return string|null
     */
    public function resolve_app_id(?string $app_id): ?string
    {
        if ($app_id === null) {
            $active_app = $this->find_active_app();
            if (!$active_app) {
                throw new \RuntimeException('No active GitHub app found.');
            }
            return $active_app['id'];
        }

        return $app_id;
    }

    /**
     * Updates a specific field in an app record.
     *
     * @param string $id The unique identifier for the app.
     * @param array<string, mixed> $fields The fields to update.
     * @return bool True if the update was successful, false otherwise.
     */
    public function update_app(string $id, array $fields): bool
    {
        if (!isset($this->cache[$id])) {
            return false; // App not found
        }

        // Merge the updated fields into the existing record
        $this->cache[$id] = array_merge($this->cache[$id], $fields);
        $this->cache[$id]['updated_at'] = current_time('mysql', true);

        $this->persist();
        return true;
    }

    /**
     * Retrieves all GitHub App connection data.
     *
     * @return array An array of associative arrays representing the apps.
     */
    public function get_all_apps(): array {
        $get_option = $this->get_option_function();
        return $get_option(Config::OPTION_APPS, []);
    }

    /**
     * Invalidate the in-memory cache.
     */
    private function invalidate_cache(): void
    {
        $this->cache = null;
    }

    /**
     * Handles legacy metadata for backward compatibility.
     *
     * @param array $metadata The metadata to process.
     * @return array The updated metadata.
     */
    public function handleLegacyMetadata(array $metadata): array
    {
        if (isset($metadata['private_key'])) {
            $decryptedKey = $this->encryption->decrypt($metadata['private_key']);
            if ($decryptedKey !== false) {
                $metadata['private_key'] = $decryptedKey;
            }
        }

        return $metadata;
    }

    /**
     * Retrieve all webhook secrets.
     *
     * @return string[] Array of webhook secrets.
     */
    public function get_all_webhook_secrets(): array
    {
        $this->load();
        $secrets = [];
        foreach ($this->cache as $data) {
            if (!empty($data['webhook_secret'])) {
                $secrets[] = $data['webhook_secret'];
            }
        }
        return $secrets;
    }
}
