<?php
declare(strict_types=1);

namespace WP2\Update;

defined('ABSPATH') || exit;

use WP2\Update\Utils\Logger;

/**
 * Class Config
 *
 * Holds constants for configuration values like option names, transient keys, and API routes.
 * This centralization prevents the use of "magic strings" throughout the codebase.
 */
class Config
{
    /**
     * Option key for storing all GitHub App connection data in the wp_options table.
     */
    public const OPTION_APPS = 'wp2_update_apps';

    /**
     * Option key for storing all package configurations in the wp_options table.
     */
    public const OPTION_PACKAGES = 'wp2_update_packages';

    /**
     * Option key for storing the unique salt used for encryption.
     */
    public const OPTION_ENCRYPTION_SALT = 'wp2_update_encryption_salt';

    /**
     * Transient key for caching plugin update data.
     */
    public const TRANSIENT_PLUGIN_UPDATES = 'wp2_plugin_updates';

    /**
     * Transient key for caching theme update data.
     */
    public const TRANSIENT_THEME_UPDATES = 'wp2_theme_updates';

    /**
     * Transient key for caching the latest release of a repository.
     * Use with sprintf: sprintf(self::TRANSIENT_LATEST_RELEASE, $owner, $repo).
     */
    public const TRANSIENT_LATEST_RELEASE = 'wp2_latest_release_%s_%s';

    /**
     * Transient key for caching the full list of repositories from a GitHub App installation.
     */
    public const TRANSIENT_REPOSITORIES_CACHE = 'wp2_repositories_cache';

    /**
     * Transient key for caching all releases of a repository.
     * Use with sprintf: sprintf(self::TRANSIENT_ALL_RELEASES, $owner, $repo).
     */
    public const TRANSIENT_ALL_RELEASES = 'wp2_update_all_releases_%s_%s';

    /**
     * REST API namespace for the plugin.
     */
    public const REST_NAMESPACE = 'wp2-update/v1';

    /**
     * The slug for the GitHub webhook endpoint.
     * Final route will be /wp-json/wp2-update/v1/webhook.
     */
    public const WEBHOOK_ROUTE_SLUG = '/webhook';

    /**
     * The base URL for the GitHub API.
     */
    public const GITHUB_API_URL = 'https://api.github.com';

    /**
     * The required WordPress capability to manage this plugin.
     */
    public const CAPABILITY = 'manage_wp2_updates';

    /**
     * Custom capabilities for RBAC.
     */
    public const CAP_MANAGE = 'manage_wp2_updates';
    public const CAP_VIEW_LOGS = 'view_wp2_update_logs';
    public const CAP_RESTORE_BACKUPS = 'restore_wp2_backups';

    /**
     * The slug for the main admin menu page.
     */
    public const MAIN_MENU_SLUG = 'wp2-update';

    /**
     * Text domain for internationalization (i18n).
     */
    public const TEXT_DOMAIN = 'wp2-update';

    /**
     * Default cache expiration time in seconds.
     */
    public const CACHE_EXPIRATION = 3600;

    /**
     * Transient key prefix for state management.
     */
    public const TRANSIENT_STATE_PREFIX = 'wp2_state_';

    /**
     * Option key for storing package data.
     */
    public const OPTION_PACKAGES_DATA = 'wp2_packages_data';

    /**
     * Option key mapping repo_slug => release channel (stable, beta, develop, alpha).
     */
    public const OPTION_RELEASE_CHANNELS = 'wp2_release_channels';

    /**
     * Nonce prefix for manifest validation.
     */
    public const NONCE_MANIFEST_PREFIX = 'wp2_manifest_';

    /**
     * Transient prefix for installation tokens.
     */
    public const TRANSIENT_INST_TOKEN_PREFIX = 'wp2_inst_token_';

    /**
     * Temporary directory prefix for updates.
     */
    public const TEMP_DIR_PREFIX = 'wp2_update_';

    /**
     * Debug mode constant to enable or disable detailed logging.
     */
    public const DEBUG_MODE = true;

    /**
     * Headless mode: when true, the admin UI is not registered. REST/CLI only.
     * Can be enabled by defining WP2_UPDATE_HEADLESS in wp-config.php
     */
    public static function headless(): bool
    {
        return defined('WP2_UPDATE_HEADLESS') && WP2_UPDATE_HEADLESS;
    }

    /**
     * Developer mode: when true, remote update checks/installs are suppressed to
     * allow local development without interference.
     * Can be enabled by defining WP2_UPDATE_DEV_MODE in wp-config.php
     */
    public static function dev_mode(): bool
    {
        return defined('WP2_UPDATE_DEV_MODE') && WP2_UPDATE_DEV_MODE;
    }

    /**
     * Base directory of the plugin.
     */
    public const PLUGIN_DIR = WP2_UPDATE_PLUGIN_DIR;

    /**
     * Option key for storing logs in the wp_options table.
     */
    public const OPTION_LOGS = 'wp2_update_logs';

    /**
     * Retrieve a filterable configuration value.
     *
     * @param string $key The configuration key to retrieve.
     * @param mixed $default The default value to return if the key is not found.
     * @return mixed The configuration value or the default value.
     */
    public static function get(string $key, $default = null)
    {
        $config = [
            'OPTION_APPS' => 'wp2_update_apps',
            'OPTION_PACKAGES' => 'wp2_update_packages',
            'OPTION_ENCRYPTION_SALT' => 'wp2_update_encryption_salt',
            'TRANSIENT_PLUGIN_UPDATES' => 'wp2_plugin_updates',
            'TRANSIENT_THEME_UPDATES' => 'wp2_theme_updates',
            'REST_NAMESPACE' => 'wp2-update/v1',
            'TEXT_DOMAIN' => 'wp2-update',
            'CACHE_EXPIRATION' => 3600,
        ];

        $value = $config[$key] ?? $default;

        // Log configuration retrieval
        if (isset($config[$key])) {
            Logger::info('Configuration value retrieved.', ['key' => $key, 'value' => $value]);
        } else {
            Logger::warning('Configuration key not found.', ['key' => $key, 'default' => $default]);
        }

        return apply_filters("wp2_update_config_{$key}", $value);
    }
}
