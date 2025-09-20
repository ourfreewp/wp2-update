<?php
namespace WP2\Update\Core;

use WP2\Update\Core\Utils\Logger\Init as Logger;
use WP2\Update\Core\Health\AppHealth;
use WP2\Update\Core\Health\RepoHealth;
use WP2\Update\Core\API\Service as GitHubService;

/**
 * Encapsulates core plugin logic, including automated tasks and health checks.
 */
class Init {
    /**
     * Runs automated health checks on all apps and repositories.
     * This method is now correctly dependent on the new CPTs.
     * It is designed to be triggered by a scheduled task.
     */
    public static function run_health_checks() {
        $github_service = new GitHubService();

        // Run health checks for all GitHub Apps
        $app_query = new \WP_Query([
            'post_type'      => 'wp2_github_app',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        if ($app_query->have_posts()) {
            foreach ($app_query->posts as $app_post_id) {
                $health = new AppHealth($app_post_id, $github_service);
                $health->run_checks();
            }
        }

        // Run health checks for all Repositories
        $repo_query = new \WP_Query([
            'post_type'      => 'wp2_repository',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        if ($repo_query->have_posts()) {
            foreach ($repo_query->posts as $repo_post_id) {
                $health = new RepoHealth($repo_post_id, $github_service);
                $health->run_checks();
            }
        }

        // Log a summary of the health check.
        Logger::log('Completed a full system health check.', 'info', 'health-check');
    }
}
