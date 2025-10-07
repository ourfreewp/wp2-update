<?php
namespace WP2\Update\Core\Updates;

use WP2\Update\Utils\SharedUtils;
use function get_transient;
use function set_transient;
use function delete_transient;
use function get_plugins;
use function wp_get_themes;
use const HOUR_IN_SECONDS;
use const MINUTE_IN_SECONDS;

/**
 * Scans the WordPress installation to find themes and plugins with a valid
 * 'Update URI' and maps them to their managing GitHub App.
 */
class PackageFinder {
    /** @var array Holds the list of managed themes. */
    private $managed_themes = [];

    /** @var array Holds the list of managed plugins. */
    private $managed_plugins = [];

    /** @var array|null Cached map of repositories to their managing app slugs. */
    private $repo_to_app_map = null;

    /** @var SharedUtils */
    private SharedUtils $utils;

    public function __construct( SharedUtils $utils ) {
        $this->utils = $utils;
        $this->scan_for_managed_themes();
        $this->scan_for_managed_plugins();

        // Clear the transient cache when an app is saved or updated.
        add_action( 'save_post_wp2_github_app', [ $this, 'clear_repo_to_app_map_cache' ] );

        // Add these action hooks
        add_action('upgrader_process_complete', [$this, 'clear_cache_on_install'], 10, 2);
        add_action('activated_plugin', [$this, 'clear_cache']);
        add_action('deactivated_plugin', [$this, 'clear_cache']);
        add_action('switch_theme', [$this, 'clear_cache']);
    }

    /**
     * Clears package cache after a theme or plugin installation/update.
     */
    public function clear_cache_on_install($upgrader, $options) {
        if (isset($options['type']) && in_array($options['type'], ['theme', 'plugin'])) {
            $this->clear_cache();
        }
    }

    /**
     * Scans all installed themes to find those with a valid "Update URI" header.
     * Results are cached in a transient for performance.
     */
    private function scan_for_managed_themes() {
        $cache_key = 'wp2_managed_themes';
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            $this->managed_themes = $cached;
            return;
        }

        $found_themes = [];
        foreach ( wp_get_themes() as $slug => $theme ) {
            $uri  = $theme->get( 'UpdateURI' ) ?: $theme->get( 'Update URI' );
            $repo = $this->utils->normalize_repo( $uri );

            if ( $repo ) {
                $app_slug = $this->find_app_for_repo( $repo );

                if ( $app_slug ) {
                    $found_themes[ $slug ] = [
                        'slug'     => $slug,
                        'repo'     => $repo,
                        'name'     => $theme->get( 'Name' ),
                        'app_slug' => $app_slug,
                    ];
                }
            }
        }

        set_transient( $cache_key, $found_themes, HOUR_IN_SECONDS );
        $this->managed_themes = $found_themes;
    }

    /**
     * Scans all installed plugins to find those with a valid "Update URI" header.
     * Results are cached in a transient for performance.
     */
    private function scan_for_managed_plugins() {
        $cache_key = 'wp2_managed_plugins';
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            $this->managed_plugins = $cached;
            return;
        }

        $found_plugins = [];
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        foreach ( get_plugins() as $slug => $plugin ) {
            $uri  = $plugin['UpdateURI'] ?? $plugin['Update URI'] ?? '';
            $repo = $this->utils->normalize_repo( $uri );

            if ( $repo ) {
                $app_slug = $this->find_app_for_repo( $repo );

                if ( $app_slug ) {
                    $found_plugins[ $slug ] = [
                        'slug'     => $slug,
                        'repo'     => $repo,
                        'name'     => $plugin['Name'],
                        'app_slug' => $app_slug,
                    ];
                }
            }
        }

        set_transient( $cache_key, $found_plugins, HOUR_IN_SECONDS );
        $this->managed_plugins = $found_plugins;
    }

    /**
     * Finds which configured app has access to a given repository.
     *
     * @param string $repo The repository slug ('owner/repo').
     * @return string|null The app slug (post_name) or null if no match is found.
     */
    private function find_app_for_repo( string $repo ): ?string {
        if ( $this->repo_to_app_map === null ) {
            $this->build_repo_to_app_map();
        }
        return $this->repo_to_app_map[ $repo ] ?? null;
    }

    /**
     * Builds a fast lookup map of all repositories to their managing app slug.
     * The result is cached in a transient to avoid querying on every page load.
     */
    private function build_repo_to_app_map() {
        $cache_key = 'wp2_repo_app_map';
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            $this->repo_to_app_map = $cached;
            return;
        }

        $this->repo_to_app_map = [];

        $query = new \WP_Query([
            'post_type'      => 'wp2_github_app',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ]);

        if ( $query->have_posts() ) {
            foreach ( $query->posts as $post ) {
                $app_slug         = $post->post_name;
                $accessible_repos = get_post_meta( $post->ID, '_wp2_accessible_repos', true );
                if ( is_array( $accessible_repos ) ) {
                    foreach ( $accessible_repos as $repo_slug ) {
                        $this->repo_to_app_map[ $repo_slug ] = $app_slug;
                    }
                }
            }
        }

        set_transient( $cache_key, $this->repo_to_app_map, 15 * MINUTE_IN_SECONDS );
    }

    /**
     * Clears all managed package caches.
     */
    public function clear_cache(): void {
        delete_transient('wp2_managed_themes');
        delete_transient('wp2_managed_plugins');
        $this->managed_themes = [];
        $this->managed_plugins = [];
    }

    /**
     * Clears the transient cache for the repo-to-app map.
     */
    public function clear_repo_to_app_map_cache() {
        delete_transient( 'wp2_repo_app_map' );
    }

    /**
     * Gets the list of managed themes.
     *
     * @return array The list of themes.
     */
    public function get_managed_themes(): array {
        return $this->managed_themes;
    }

    /**
     * Gets the list of managed plugins.
     *
     * @return array List of managed plugins.
     */
    public function get_managed_plugins(): array {
        return $this->managed_plugins;
    }

    /**
     * Gets all managed packages, both themes and plugins, in a single unified array.
     *
     * @return array A list of all managed packages.
     */
    public function get_managed_packages(): array {
        $themes = array_map(function($theme) {
            $theme['type'] = 'theme';
            return $theme;
        }, $this->managed_themes);

        $plugins = array_map(function($plugin) {
            $plugin['type'] = 'plugin';
            return $plugin;
        }, $this->managed_plugins);

        return array_merge($themes, $plugins);
    }

    /**
     * Retrieves a list of available updates for managed themes and plugins.
     *
     * @return array<string, string> An associative array where the key is the package name and the value is the version update string.
     */
    public static function get_available_updates(): array {
        $updates = [];

        // Example logic for fetching updates. Replace with actual implementation.
        $updates['Plugin A'] = '1.2.3 -> 1.2.4';
        $updates['Plugin B'] = '2.0.0 -> 2.1.0';

        return $updates;
    }

    /**
     * Returns a list of items (themes and plugins) that need updates.
     *
     * @return array List of items to update.
     */
    public function get_items_to_update(): array {
        $items_to_update = [];

        foreach ($this->managed_themes as $theme) {
            if ($this->utils->is_update_available($theme['slug'])) {
                $items_to_update[] = [
                    'type' => 'theme',
                    'name' => $theme['name'],
                    'slug' => $theme['slug'],
                ];
            }
        }

        foreach ($this->managed_plugins as $plugin) {
            if ($this->utils->is_update_available($plugin['slug'])) {
                $items_to_update[] = [
                    'type' => 'plugin',
                    'name' => $plugin['name'],
                    'slug' => $plugin['slug'],
                ];
            }
        }

        return $items_to_update;
    }
}
