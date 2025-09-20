<?php
namespace WP2\Update\Tasks;

use WP2\Update\Core\Health\AppHealth;
use WP2\Update\Core\Health\RepoHealth;

require_once ABSPATH . 'wp-content/plugins/wp2-update/vendor/woocommerce/action-scheduler/action-scheduler.php';

final class Scheduler {
    // --- Hook Definitions ---
    const SYNC_APPS_HOOK = 'wp2_sync_all_github_apps';
    const HEALTH_CHECK_ALL_APPS_HOOK = 'wp2_health_check_all_apps';
    const HEALTH_CHECK_SINGLE_APP_HOOK = 'wp2_health_check_single_app';
    const HEALTH_CHECK_ALL_REPOS_HOOK = 'wp2_health_check_all_repositories';
    const HEALTH_CHECK_SINGLE_REPO_HOOK = 'wp2_health_check_single_repository';

    public static function init() {
        // Sync Task
        add_action(self::SYNC_APPS_HOOK, [__NAMESPACE__ . '\Sync', 'run_sync']);

        // Health Check Tasks
        add_action(self::HEALTH_CHECK_ALL_APPS_HOOK, [__CLASS__, 'run_all_app_checks']);
        add_action(self::HEALTH_CHECK_SINGLE_APP_HOOK, [__CLASS__, 'run_single_app_check'], 10, 1);
        add_action(self::HEALTH_CHECK_ALL_REPOS_HOOK, [__CLASS__, 'run_all_repo_checks']);
        add_action(self::HEALTH_CHECK_SINGLE_REPO_HOOK, [__CLASS__, 'run_single_repo_check'], 10, 1);
    }
    
    // --- Scheduler Methods (Public API for our plugin) ---

    public static function schedule_recurring_tasks() {
        // Schedule main sync to run hourly.
        if (as_next_scheduled_action(self::SYNC_APPS_HOOK) === false) {
            as_schedule_recurring_action(time(), HOUR_IN_SECONDS, self::SYNC_APPS_HOOK, [], 'WP2 Update');
        }
        // Schedule a full system health check to run daily.
        if (as_next_scheduled_action(self::HEALTH_CHECK_ALL_APPS_HOOK) === false) {
            as_schedule_recurring_action(time() + (5 * MINUTE_IN_SECONDS), DAY_IN_SECONDS, self::HEALTH_CHECK_ALL_APPS_HOOK, [], 'WP2 Update');
        }
    }

    public static function schedule_health_check_for_repo(int $repo_post_id) {
        as_enqueue_async_action(self::HEALTH_CHECK_SINGLE_REPO_HOOK, ['repo_post_id' => $repo_post_id], 'WP2 Update');
    }

    // --- Action Callbacks ---

    public static function run_all_app_checks() {
        $query = new \WP_Query(['post_type' => 'wp2_github_app', 'fields' => 'ids', 'posts_per_page' => -1]);
        if ($query->have_posts()) {
            foreach ($query->posts as $app_post_id) {
                as_enqueue_async_action(self::HEALTH_CHECK_SINGLE_APP_HOOK, ['app_post_id' => $app_post_id], 'WP2 Update');
            }
        }
    }

    public static function run_single_app_check(array $args) {
        if (isset($args['app_post_id'])) {
            $github_service = new \WP2\Update\Core\API\Service(); // Instantiate GitHubService
            (new AppHealth($args['app_post_id'], $github_service))->run_checks();
        }
    }
    
    public static function run_all_repo_checks() {
        $query = new \WP_Query(['post_type' => 'wp2_repository', 'fields' => 'ids', 'posts_per_page' => -1]);
        if ($query->have_posts()) {
            foreach ($query->posts as $repo_post_id) {
                self::schedule_health_check_for_repo($repo_post_id);
            }
        }
    }
    
    public static function run_single_repo_check(array $args) {
        if (isset($args['repo_post_id'])) {
            $github_service = new \WP2\Update\Core\API\Service(); // Instantiate GitHubService
            (new RepoHealth($args['repo_post_id'], $github_service))->run_checks();
        }
    }
}

