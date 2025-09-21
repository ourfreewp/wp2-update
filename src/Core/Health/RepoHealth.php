<?php
namespace WP2\Update\Core\Health;

use WP2\Update\Core\API\GitHubApp\Init as GitHubApp;
use WP2\Update\Core\API\Service as GitHubService;

/**
 * Handles scheduling and validating the health of wp2_repository posts.
 */
class RepoHealth
{
    /**
     * The post ID of the wp2_repository CPT.
     * @var int
     */
    private int $repo_post_id;
    private GitHubService $github_service;

    public function __construct(int $repo_post_id, GitHubService $github_service)
    {
        $this->repo_post_id = $repo_post_id;
        $this->github_service = $github_service;
    }

    /**
     * Schedules an individual health check for every repository.
     *
     * Callback for the `HEALTH_CHECK_ALL_REPOS_HOOK`.
     */
    public static function check_all_repos()
    {
        $query = new \WP_Query([
            'post_type'      => 'wp2_repository',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        if ($query->have_posts()) {
            foreach ($query->posts as $repo_post_id) {
                // Schedule each check as its own asynchronous action.
                \WP2\Update\Core\Tasks\Scheduler::schedule_health_check_for_repo($repo_post_id);
            }
        }
    }

    /**
     * Validates a single repository's configuration.
     *
     * Callback for the `HEALTH_CHECK_SINGLE_REPO_HOOK`.
     * Note: Action Scheduler passes arguments in an array.
     *
     * @param array $args An array containing the 'repo_post_id'.
     */
    public function check_single_repo(array $args)
    {
        if (!isset($args['repo_post_id'])) {
            return;
        }
        $repo_post_id = $args['repo_post_id'];
        $github_service = $this->github_service;
        (new self($repo_post_id, $github_service))->run_checks();
    }

    /**
     * Runs all health checks for the Repository.
     */
    public function run_checks()
    {
        // 1. Check if it's linked to a valid app.
        $app_post_id = get_post_meta($this->repo_post_id, '_managing_app_post_id', true);
        if (!$app_post_id || !get_post($app_post_id)) {
            $this->update_status('error', 'Configuration error: This repository is not linked to a valid managing app.');
            return;
        }

        // 2. Check if the linked app itself is healthy.
        $app_health = get_post_meta($app_post_id, '_health_status', true);
        if ($app_health !== 'ok') {
            $this->update_status('error', 'Dependency error: The managing app (' . esc_html(get_the_title($app_post_id)) . ') is not healthy.');
            return;
        }

        // 3. Final validation: Use the app's token to access this repo's API endpoint.
        $app_slug  = get_post_field('post_name', $app_post_id);
        $repo_slug = get_post_field('post_name', $this->repo_post_id);
        
        $github_app = new GitHubApp($this->github_service);
        $response = $github_app->gh($app_slug, 'GET', "/repos/{$repo_slug}");

        if ($response['ok']) {
            $this->update_status('ok', 'Successfully connected and validated repository access.');
        } else {
            $this->update_status('error', 'API validation failed: The managing app may no longer have permission to access this repository. Error: ' . esc_html($response['error'] ?? 'Unknown'));
        }
    }

    /**
     * Updates the health status meta fields for the repository post.
     */
    private function update_status(string $status, string $message)
    {
        update_post_meta($this->repo_post_id, '_health_status', $status);
        update_post_meta($this->repo_post_id, '_health_message', $message);
        update_post_meta($this->repo_post_id, '_last_checked_timestamp', time());
    }
}
