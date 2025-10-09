<?php

namespace WP2\Update;

class Config
{
    // Option keys
    public const OPTION_CREDENTIALS = 'wp2_github_app_credentials';

    // Transient keys
    public const TRANSIENT_PLUGIN_UPDATES = 'wp2_plugin_updates';
    public const TRANSIENT_THEME_UPDATES = 'wp2_theme_updates';
    public const TRANSIENT_LATEST_RELEASE = 'wp2_latest_release_%s_%s'; // owner, repo
    public const TRANSIENT_REPOSITORIES_CACHE = 'wp2_repositories_cache';
    /**
     * Transient key for caching all releases of a repository.
     */
    public const TRANSIENT_ALL_RELEASES = 'wp2_update_all_releases_%s_%s';

    // API routes
    public const API_ROUTE_CONNECTION = '/wp2/v1/connection';
    public const API_ROUTE_CREDENTIALS = '/wp2/v1/credentials';
    public const API_ROUTE_PACKAGES = '/wp2/v1/packages';

    // Text domain for translations
    public const TEXT_DOMAIN = 'wp2-update';
}