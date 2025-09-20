<?php
namespace WP2\Update\Core\Sync;

use WP2\Update\Core\API\Service as GitHubService;
use WP2\Update\Core\Tasks\Scheduler as TaskManager;
use WP2\Update\Utils\Logger;

/**
 * Manages the background synchronization of repositories from GitHub.
 */
final class Repos {
    private GitHubService $github_service;

    public function __construct(GitHubService $github_service) {
        $this->github_service = $github_service;
    }

    /**
     * The main sync function, executed by Action Scheduler.
     */
    public function run(): void {
        $query = new \WP_Query([
            'post_type'      => 'wp2_github_app',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ]);

        if (!$query->have_posts()) {
            Logger::log('No GitHub Apps found to sync.', 'info', 'sync');
            return;
        }

        foreach ($query->posts as $app_post_id) {
            $this->sync_repositories_for_app($app_post_id);
        }
    }

    /**
     * Fetches and processes all accessible repositories for a single GitHub App.
     */
    public function sync_repositories_for_app(int $app_post_id): void {
        $app_slug = get_post_field('post_name', $app_post_id);
        if (!$app_slug) {
            Logger::log('Skipping sync: Could not find app slug for post ID ' . $app_post_id, 'error', 'sync');
            return;
        }

        // This call is now correctly using the new Service class with the app slug
        $accessible_repos = $this->github_service->fetch_all_paginated(
            $app_slug,
            'apps',
            'listInstallationRepositories',
            ['installation_id' => get_post_meta($app_post_id, '_wp2_installation_id', true)]
        );

        if (empty($accessible_repos)) {
            Logger::log('No accessible repositories found for app: ' . $app_slug, 'info', 'sync');
            // Clear the accessible repos meta if the list is empty
            update_post_meta($app_post_id, '_wp2_accessible_repos', []);
            return;
        }

        $repo_slugs = [];
        foreach ($accessible_repos as $repo_data) {
            $repo_slug = $repo_data['full_name'];
            $repo_slugs[] = $repo_slug;
            $repo_post_id = $this->create_or_update_repository_post($repo_data, $app_post_id);

            if ($repo_post_id) {
                TaskManager::schedule_health_check_for_repo($repo_post_id);
            }
        }
        
        // CRITICAL FIX: Update the accessible repositories meta for the app post.
        // This is the missing link that allows Connection to map repos to apps.
        update_post_meta($app_post_id, '_wp2_accessible_repos', $repo_slugs);
        
        Logger::log(
            'Successfully synced ' . count($repo_slugs) . ' repositories for app: ' . $app_slug, 
            'success', 
            'sync'
        );
    }

    /**
     * Creates or updates a wp2_repository post.
     */
    private function create_or_update_repository_post(array $repo_data, int $app_post_id): ?int {
        $repo_full_name = $repo_data['full_name'];
        $existing_post = get_page_by_path($repo_full_name, OBJECT, 'wp2_repository');

        $post_args = [
            'post_type'    => 'wp2_repository',
            'post_title'   => $repo_full_name,
            'post_name'    => $repo_full_name,
            'post_content' => $repo_data['description'] ?? '',
            'post_status'  => 'publish',
        ];

        $repo_post_id = $existing_post ? $existing_post->ID : 0;

        if ($repo_post_id) {
            $post_args['ID'] = $repo_post_id;
            wp_update_post($post_args);
        } else {
            $repo_post_id = wp_insert_post($post_args);
        }

        if ($repo_post_id && !is_wp_error($repo_post_id)) {
            update_post_meta($repo_post_id, '_managing_app_post_id', $app_post_id);
            update_post_meta($repo_post_id, '_github_id', $repo_data['id']);
            update_post_meta($repo_post_id, '_is_private', $repo_data['private']);
            update_post_meta($repo_post_id, '_html_url', $repo_data['html_url']);
            update_post_meta($repo_post_id, '_last_synced_timestamp', time());
            return (int) $repo_post_id;
        }
        return null;
    }
}
