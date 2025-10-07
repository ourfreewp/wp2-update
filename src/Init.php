<?php

namespace WP2\Update;

// Prevent multiple inclusions of this file.
if (class_exists(__NAMESPACE__ . '\\Init', false)) {
    return;
}

use WP2\Update\Admin\Init as AdminInit;
use WP2\Update\Admin\Models\Init as ModelsInit;
use WP2\Update\Core\API\GitHubApp\Init as GitHubApp;
use WP2\Update\Core\API\Service;
use WP2\Update\Core\Updates\PackageFinder;
use WP2\Update\Core\Updates\PluginUpdater;
use WP2\Update\Core\Updates\ThemeUpdater;
use WP2\Update\Utils\SharedUtils;

/**
 * Main bootstrap class for the plugin.
 */
final class Init
{
    private Service $githubService;
    private SharedUtils $utils;
    private GitHubApp $githubApp;
    private PackageFinder $packageFinder;
    private PluginUpdater $pluginUpdater;
    private ThemeUpdater $themeUpdater;
    private AdminInit $admin;
    private ModelsInit $models;

    /**
     * Entry point called from the plugin loader.
     */
    public static function boot(): void
    {
        $instance = new self();
        $instance->register();
    }

    public function __construct()
    {
        $githubClient = new \Github\Client();
        $this->githubService = new Service($githubClient);
        $this->githubApp = new GitHubApp($this->githubService);
        $this->utils = new SharedUtils($this->githubApp, $this->githubService);
        $this->packageFinder = new PackageFinder($this->utils);
        $this->pluginUpdater = new PluginUpdater($this->packageFinder, $this->githubService, $this->utils);
        $this->themeUpdater  = new ThemeUpdater($this->packageFinder, $this->githubService, $this->utils);
        $this->admin         = new AdminInit($this->githubService, $this->packageFinder, $this->utils, $this->githubApp);
        $this->models        = new ModelsInit();
    }

    /**
     * Register WordPress hooks for the simplified plugin.
     */
    private function register(): void
    {
        $this->models->register();
        $this->admin->register_hooks();
        $this->pluginUpdater->register_hooks();
        $this->themeUpdater->register_hooks();

        add_action('init', [self::class, 'load_textdomain']);
    }

    /**
     * Loads the plugin textdomain for translations.
     */
    public static function load_textdomain(): void
    {
        load_plugin_textdomain('wp2-update', false, dirname(WP2_UPDATE_PLUGIN_FILE) . '/languages');
    }
}
