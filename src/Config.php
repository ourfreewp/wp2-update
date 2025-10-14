<?php

namespace WP2\Update;

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
    public const CAPABILITY = 'manage_options';

    /**
     * The slug for the main admin menu page.
     */
    public const MAIN_MENU_SLUG = 'wp2-update';

    /**
     * Text domain for internationalization (i18n).
     */
    public const TEXT_DOMAIN = 'wp2-update';

    /**
     * Name of the custom database table for logs.
     */
    public const LOGS_TABLE_NAME = 'wp2_update_logs';

    /**
     * The number of days to retain logs before pruning.
     */
    public const LOG_RETENTION_DAYS = 30;

    /**
     * Default cache expiration time in seconds.
     */
    public const CACHE_EXPIRATION = 3600;
}

