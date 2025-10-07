<?php
namespace WP2\Update\Admin\Pages;

use WP2\Update\Core\Connection\Init as Connection;
use WP2\Update\Core\API\GitHubApp\Init as GitHubApp;
use WP2\Update\Utils\SharedUtils;

class SystemHealthPage {
    private $connection;
    private $github_app;
    private $utils;

    public function __construct(Connection $connection, GitHubApp $github_app, SharedUtils $utils) {
        $this->connection = $connection;
        $this->github_app = $github_app;
        $this->utils = $utils;
    }

    private function render_health_section($title, $items) {
        ?>
        <h2 class="wp2-health-header"><?php echo esc_html($title); ?></h2>
        <table class="wp2-data-table wp2-data-table--health">
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="cell-label"><?php echo esc_html($item['label']); ?></td>
                        <td><?php echo ! empty( $item['allow_html'] ) ? $item['value'] : wp_kses_post( $item['value'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    private function get_wp_environment() {
        global $wp_version;
        return [
            ['label' => 'Home URL', 'value' => '<code>' . get_home_url() . '</code>'],
            ['label' => 'Site URL', 'value' => '<code>' . get_site_url() . '</code>'],
            ['label' => 'WordPress Version', 'value' => $wp_version],
            ['label' => 'Multisite', 'value' => is_multisite() ? 'Yes' : 'No'],
            ['label' => 'WP Memory Limit', 'value' => WP_MEMORY_LIMIT],
            ['label' => 'WP Cron', 'value' => (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) ? '<span class="status-danger">Disabled</span>' : '<span class="status-success">Enabled</span>'],
        ];
    }

    private function get_server_environment() {
        return [
            ['label' => 'Server Info', 'value' => esc_html( $_SERVER['SERVER_SOFTWARE'] ?? 'N/A' )],
            ['label' => 'PHP Version', 'value' => phpversion()],
            ['label' => 'PHP Memory Limit', 'value' => ini_get('memory_limit')],
            ['label' => 'cURL Version', 'value' => function_exists('curl_version') ? curl_version()['version'] : 'N/A'],
        ];
    }

    public function get_github_api_status() {
        $items = [];
        $apps_query = new \WP_Query([
            'post_type'      => 'wp2_github_app',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true, // Optimization: Disable pagination overhead
        ]);
        if (!$apps_query->have_posts()) {
            $items[] = ['label' => 'Status', 'value' => 'No GitHub Apps configured.'];
        } else {
            foreach ($apps_query->posts as $post) {
                if (!is_object($post)) {
                    continue; // Skip invalid posts
                }

                $status = get_post_meta($post->ID, '_health_status', true);
                $message = get_post_meta($post->ID, '_health_message', true);
                $last_checked = get_post_meta($post->ID, '_last_checked_timestamp', true);
                $status_text = $status === 'ok' ? '<span class="status-success">Connected</span>' : '<span class="status-danger">Error</span>';

                $items[] = ['label' => 'App: ' . esc_html($post->post_title), 'value' => $status_text];
                $items[] = ['label' => 'Message', 'value' => esc_html($message)];
                $items[] = ['label' => 'Last Checked', 'value' => $last_checked ? human_time_diff($last_checked) . ' ago' : 'Never'];
            }
        }
        return $items;
    }
    
    public function get_repo_health() {
        $items = [];
        $repos_query = new \WP_Query([
            'post_type'      => 'wp2_repository',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true, // Optimization: Disable pagination overhead
        ]);
        if (!$repos_query->have_posts()) {
            $items[] = ['label' => 'Status', 'value' => 'No managed repositories found.'];
        } else {
            foreach ($repos_query->posts as $post) {
                $status = get_post_meta($post->ID, '_health_status', true);
                $message = get_post_meta($post->ID, '_health_message', true);
                $last_checked = get_post_meta($post->ID, '_last_checked_timestamp', true);
                $status_text = $status === 'ok' ? '<span class="status-success">Healthy</span>' : '<span class="status-danger">Error</span>';
                $items[] = ['label' => 'Repo: ' . esc_html($post->post_title), 'value' => $status_text];
                $items[] = ['label' => 'Message', 'value' => esc_html($message)];
                $items[] = ['label' => 'Last Checked', 'value' => $last_checked ? human_time_diff($last_checked) . ' ago' : 'Never'];
            }
        }
        return $items;
    }

    private function get_plugin_cache_status() {
        $themes_transient = get_site_transient('update_themes');
        $plugins_transient = get_site_transient('update_plugins');

        $rows = [
            ['label' => 'Managed Themes (Cached)', 'value' => count($this->connection->get_managed_themes())],
            ['label' => 'Managed Plugins (Cached)', 'value' => count($this->connection->get_managed_plugins())],
            ['label' => 'Update Check Transient (Themes)', 'value' => $themes_transient ? '<span class="status-success">Exists</span>' : '<span class="status-info">Not set</span>'],
            ['label' => 'Update Check Transient (Plugins)', 'value' => $plugins_transient ? '<span class="status-success">Exists</span>' : '<span class="status-info">Not set</span>'],
            [
                'label' => 'Actions',
                'value' => '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=wp2-update-system-health&force-check=1' ), 'wp2-force-check' ) ) . '" class="button button-secondary wp2-clear-cache-button">' . esc_html__( 'Clear All Caches & Force Check', 'wp2-update' ) . '</a>'
            ],
        ];

        $rows[] = [
            'label' => __( 'Manual Scheduler', 'wp2-update' ),
            'value' => $this->get_manual_scheduler_form(),
            'allow_html' => true,
        ];

        return $rows;
    }

    private function get_events_log() {
        $events_list_table = new \WP2\Update\Admin\Tables\EventsListTable();
        ob_start();
        $events_list_table->prepare_items();
        $events_list_table->display();
        return ob_get_clean();
    }

    private function get_manual_scheduler_form(): string {
        ob_start();
        ?>
        <?php
        $manual_sync_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=wp2_run_scheduler' ),
            'wp2_run_scheduler_action'
        );
        ?>
        <button
            type="button"
            class="button button-primary"
            id="wp2-run-manual-sync"
            data-url="<?php echo esc_url( $manual_sync_url ); ?>"
            data-running-label="<?php echo esc_attr__( 'Running…', 'wp2-update' ); ?>"
        >
            <?php esc_html_e( 'Run Sync & Health Checks Now', 'wp2-update' ); ?>
        </button>
        <?php
        return ob_get_clean();
    }

    public function handle_update_check() {
        if ( isset( $_GET['update-check'] ) && '1' === $_GET['update-check'] ) {
            // Verify nonce to ensure the request is valid.
            $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'wp2-force-check' ) ) {
                error_log( 'SystemHealthPage: Invalid nonce for update-check.' );
                wp_die( 
                    __( 'Invalid request. Nonce verification failed.', 'wp2-update' ), 
                    __( 'Forbidden', 'wp2-update' ), 
                    [ 'response' => 403 ] 
                );
            }

            // Log the redirection for debugging
            error_log( 'SystemHealthPage: Handling update-check redirection.' );

            // Clear the parameter to prevent redirection loops
            $redirect_url = remove_query_arg( 'update-check' );
            if ( strpos( $redirect_url, 'update-check' ) === false ) {
                wp_safe_redirect( $redirect_url );
                exit;
            } else {
                error_log( 'SystemHealthPage: Redirection failed to remove update-check parameter.' );
            }
        }
    }

    public function render() {
        ?>
        <div class="wrap wp2-update-page">
            <?php
            $manual_sync_status = isset( $_GET['manual-sync'] ) ? sanitize_text_field( wp_unslash( $_GET['manual-sync'] ) ) : '';
            if ( 'success' === $manual_sync_status ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Manual sync completed successfully.', 'wp2-update' ) . '</p></div>';
            } elseif ( 'error' === $manual_sync_status ) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Manual sync failed. Check the logs for details.', 'wp2-update' ) . '</p></div>';
            }
            ?>
            <div class="wp2-update-header">
                <h1><?php esc_html_e( 'System Health', 'wp2-update' ); ?></h1>
                <p class="description"><?php esc_html_e( 'Detailed debug and environment information for troubleshooting.', 'wp2-update' ); ?></p>
            </div>

            <div class="wp2-update-card">
                <div class="wp2-container">
                    <?php $this->render_health_section('GitHub App Status', $this->get_github_api_status()); ?>
                    <?php $this->render_health_section('Repository Health', $this->get_repo_health()); ?>
                    <?php $this->render_health_section('Plugin & Cache Status', $this->get_plugin_cache_status()); ?>
                    <?php $this->render_health_section('WordPress Environment', $this->get_wp_environment()); ?>
                    <?php $this->render_health_section('Server Environment', $this->get_server_environment()); ?>
                    
                    <!-- Add Events Log Section -->
                    <h2 class="wp2-health-header"><?php esc_html_e( 'Events Log', 'wp2-update' ); ?></h2>
                    <div class="wp2-events-log">
                        <?php echo $this->get_events_log(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
