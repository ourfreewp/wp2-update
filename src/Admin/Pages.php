<?php

namespace WP2\Update\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use WP2\Update\Core\API\GitHubApp\Init as GitHubApp;
use WP2\Update\Core\API\Service as GitHubService;
use WP2\Update\Core\Updates\PackageFinder;

/**
 * Renders the multi-stage admin page.
 */
class Pages
{
    private GitHubService $githubService;
    private PackageFinder $packages;
    private GitHubApp $githubApp;

    public function __construct(GitHubService $githubService, PackageFinder $packages, GitHubApp $githubApp)
    {
        $this->githubService = $githubService;
        $this->packages      = $packages;
        $this->githubApp     = $githubApp;
    }

    /**
     * Main render function - outputs the root div for the JavaScript app.
     */
    public function render(): void
    {
        echo '<div id="wp2-update-app" class="wrap"></div>';
    }

    /**
     * Renders the HTML for the GitHub callback handling.
     */
    public function render_callback(): void
    {
        // Enqueue the GitHub callback script
        wp_enqueue_script(
            'wp2-update-github-callback',
            WP2_UPDATE_PLUGIN_URL . 'assets/scripts/github-callback.js',
            [],
            '1.0.0',
            true
        );

        // Output a minimal HTML structure
        echo '<div id="wp2-update-github-callback"></div>';
    }
}