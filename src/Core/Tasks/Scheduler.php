<?php
namespace WP2\Update\Core\Tasks;

use WP2\Update\Core\Health\AppHealth;
use WP2\Update\Core\Health\RepoHealth;
use WP2\Update\Core\Sync\Repos;
use WP2\Update\Core\API\Service as GitHubService;
use ActionScheduler;
use WP2\Update\Utils\Logger;

/**
 * Manages all Action Scheduler tasks.
 */
final class Scheduler {
    // --- Hook Definitions ---
    const SYNC_ALL_REPOS_HOOK = 'wp2_sync_all_repos';
    const HEALTH_CHECK_ALL_APPS_HOOK = 'wp2_health_check_all_apps';
    const HEALTH_CHECK_SINGLE_APP_HOOK = 'wp2_health_check_single_app';
    const HEALTH_CHECK_ALL_REPOS_HOOK = 'wp2_health_check_all_repositories';
    const HEALTH_CHECK_SINGLE_REPO_HOOK = 'wp2_health_check_single_repository';
    const SYNC_APP_REPOS_HOOK = 'wp2_sync_app_repos'; // New hook
    const RETRY_FAILED_SYNC_HOOK = 'wp2_retry_failed_sync'; // New hook for retrying failed syncs

    private GitHubService $githubService;

    // Define constants for scheduling delays.
    private const SYNC_DELAY = 0; // No delay for sync.
    private const HEALTH_CHECK_DELAY = 5 * MINUTE_IN_SECONDS;

    public function __construct(GitHubService $githubService) {
        $this->githubService = $githubService;
    }

    public function init_hooks() {
        // Check if Action Scheduler is loaded before adding hooks.
        if (!function_exists('as_enqueue_async_action')) {
            add_action('admin_notices', [$this, 'action_scheduler_notice']);
            return;
        }

        // Include the bundled version if Action Scheduler isn't active.
        if (!class_exists('ActionScheduler_Versions') && !function_exists('action_scheduler_register_1_dot_0')) {
            require_once WP2_UPDATE_PLUGIN_DIR . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
        }

        // Sync Task
        add_action(self::SYNC_ALL_REPOS_HOOK, [$this, 'run_sync_all_repos']);
        add_action(self::SYNC_APP_REPOS_HOOK, [$this, 'run_sync_for_app'], 10, 1);

        // Health Check Tasks
        add_action(self::HEALTH_CHECK_ALL_APPS_HOOK, [$this, 'run_all_app_checks']);
        add_action(self::HEALTH_CHECK_SINGLE_APP_HOOK, [$this, 'run_single_app_check'], 10, 1);
        add_action(self::HEALTH_CHECK_ALL_REPOS_HOOK, [$this, 'run_all_repo_checks']);
        add_action(self::HEALTH_CHECK_SINGLE_REPO_HOOK, [$this, 'run_single_repo_check'], 10, 1);

        // Retry Failed Sync Task
        add_action(self::RETRY_FAILED_SYNC_HOOK, [$this, 'retry_failed_sync'], 10, 1);
    }

    // --- Scheduler Methods (Public API for our plugin) ---

    public static function schedule_recurring_tasks() {
        // Ensure Action Scheduler is initialized before scheduling tasks.
        if (!class_exists('ActionScheduler') || !ActionScheduler::is_initialized()) {
            return;
        }

        // Schedule main sync to run hourly.
        if (as_next_scheduled_action(self::SYNC_ALL_REPOS_HOOK) === false) {
            as_schedule_recurring_action(time() + self::SYNC_DELAY, HOUR_IN_SECONDS, self::SYNC_ALL_REPOS_HOOK, [], 'WP2 Update');
        }
        // Schedule a full system health check to run daily.
        if (as_next_scheduled_action(self::HEALTH_CHECK_ALL_APPS_HOOK) === false) {
            as_schedule_recurring_action(time() + self::HEALTH_CHECK_DELAY, DAY_IN_SECONDS, self::HEALTH_CHECK_ALL_APPS_HOOK, [], 'WP2 Update');
        }
    }

    public static function schedule_health_check_for_repo(int $repo_post_id) {
        as_enqueue_async_action(self::HEALTH_CHECK_SINGLE_REPO_HOOK, ['repo_post_id' => $repo_post_id], 'WP2 Update');
    }

    public static function schedule_sync_for_app(int $app_post_id) {
        as_enqueue_async_action(self::SYNC_APP_REPOS_HOOK, ['app_post_id' => $app_post_id], 'WP2 Update');
    }

    public static function schedule_health_check_for_app(int $app_post_id) {
        as_enqueue_async_action(self::HEALTH_CHECK_SINGLE_APP_HOOK, ['app_post_id' => $app_post_id], 'WP2 Update');
    }

    public static function schedule_retry_for_failed_sync(int $repo_post_id) {
        as_enqueue_async_action(self::RETRY_FAILED_SYNC_HOOK, ['repo_post_id' => $repo_post_id], 'WP2 Update');
    }

    // --- Action Callbacks ---

    public function run_sync_all_repos() {
        $github_service = $this->githubService;
        (new Repos($github_service))->run();
    }
    
    public static function run_all_app_checks() {
        $query = new \WP_Query([
            'post_type'      => 'wp2_github_app',
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'no_found_rows'  => true, // Optimization: Disable pagination overhead
        ]);
        if ($query->have_posts()) {
            $delay = 0;
            foreach ($query->posts as $app_post_id) {
                as_enqueue_async_action(self::HEALTH_CHECK_SINGLE_APP_HOOK, ['app_post_id' => $app_post_id], 'WP2 Update', time() + $delay);
                $delay += 60; // Stagger by 1 minute
            }
        }
    }

    public function run_single_app_check(array $args) {
        if (isset($args['app_post_id'])) {
            $github_service = $this->githubService;
            (new AppHealth($args['app_post_id'], $github_service))->run_checks();
        }
    }
    
    public static function run_all_repo_checks() {
        $query = new \WP_Query([
            'post_type'      => 'wp2_repository',
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'no_found_rows'  => true, // Optimization: Disable pagination overhead
        ]);
        if ($query->have_posts()) {
            $delay = 0;
            foreach ($query->posts as $repo_post_id) {
                as_enqueue_async_action(self::HEALTH_CHECK_SINGLE_REPO_HOOK, ['repo_post_id' => $repo_post_id], 'WP2 Update', time() + $delay);
                $delay += 60; // Stagger by 1 minute
            }
        }
    }
    
    public function run_single_repo_check(array $args) {
        if (isset($args['repo_post_id'])) {
            $github_service = $this->githubService;
            (new RepoHealth($args['repo_post_id'], $github_service))->run_checks();
        }
    }

    public static function run_sync_for_app($args, GitHubService $github_service = null) {
        if (!is_array($args)) {
            $args = ['app_post_id' => $args];
        }
        if (isset($args['app_post_id'])) {
            if (!$github_service) {
                // Use the DI container to fetch the GitHubService instance.
                $container = \WP2\Update\Init::get_container();
                if ($container) {
                    $github_service = $container->resolve('GitHubService');
                } else {
                    Logger::log('DI container is not available.', 'error', 'tasks');
                    return;
                }
            }
            $repos = new Repos($github_service);
            $repos->sync_repositories_for_app($args['app_post_id']);
        }
    }

    public function retry_failed_sync(array $args) {
        if (isset($args['repo_post_id'])) {
            $repo_post_id = $args['repo_post_id'];
            $github_service = $this->githubService;

            try {
                (new Repos($github_service))->sync_single_repo($repo_post_id);
                Logger::info("Retry successful for repo ID: $repo_post_id", 'sync');
            } catch (\Exception $e) {
                Logger::error("Retry failed for repo ID: $repo_post_id. Error: " . $e->getMessage(), 'sync');
                // Optionally, reschedule the retry again after a delay.
                as_schedule_single_action(time() + 5 * MINUTE_IN_SECONDS, self::RETRY_FAILED_SYNC_HOOK, ['repo_post_id' => $repo_post_id], 'WP2 Update');
            }
        }
    }

    public function action_scheduler_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e( 'WP2 Update requires the Action Scheduler library to function, but it was not found. Please ensure it is installed and activated.', 'wp2-update' ); ?></p>
        </div>
        <?php
    }

    public static function getInstance(): Scheduler {
        static $instance = null;
        if ($instance === null) {
            $container = apply_filters('wp2_update_di_container', null);
            if ($container === null) {
                throw new \RuntimeException('DI Container is not initialized.');
            }
            $instance = new self($container->resolve('GitHubService'));
        }
        return $instance;
    }
}
