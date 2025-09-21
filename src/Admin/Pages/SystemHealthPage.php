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

    public function render() {
        ?>
        <div class="wrap wp2-update-page">
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
                </div>
            </div>
        </div>
        <?php
    }

    private function render_health_section($title, $items) {
        ?>
        <h2 class="wp2-health-header"><?php echo esc_html($title); ?></h2>
        <table class="wp2-data-table wp2-data-table--health">
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="cell-label"><?php echo esc_html($item['label']); ?></td>
                        <td><?php echo wp_kses_post($item['value']); ?></td>
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
            ['label' => 'Server Info', 'value' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'],
            ['label' => 'PHP Version', 'value' => phpversion()],
            ['label' => 'PHP Memory Limit', 'value' => ini_get('memory_limit')],
            ['label' => 'cURL Version', 'value' => function_exists('curl_version') ? curl_version()['version'] : 'N/A'],
        ];
    }

    public function get_github_api_status() {
        $items = [];
        $apps_query = new \WP_Query(['post_type' => 'wp2_github_app', 'posts_per_page' => -1]);
        if (!$apps_query->have_posts()) {
            $items[] = ['label' => 'Status', 'value' => 'No GitHub Apps configured.'];
        } else {
            foreach ($apps_query->posts as $post) {
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
        $repos_query = new \WP_Query(['post_type' => 'wp2_repository', 'posts_per_page' => -1]);
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

        return [
            ['label' => 'Managed Themes (Cached)', 'value' => count($this->connection->get_managed_themes())],
            ['label' => 'Managed Plugins (Cached)', 'value' => count($this->connection->get_managed_plugins())],
            ['label' => 'Update Check Transient (Themes)', 'value' => $themes_transient ? '<span class="status-success">Exists</span>' : '<span class="status-info">Not set</span>'],
            ['label' => 'Update Check Transient (Plugins)', 'value' => $plugins_transient ? '<span class="status-success">Exists</span>' : '<span class="status-info">Not set</span>'],
            [
                'label' => 'Actions',
                'value' => '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=wp2-update-system-health&force-check=1' ), 'wp2-force-check' ) ) . '" class="button button-secondary wp2-clear-cache-button">' . esc_html__( 'Clear All Caches & Force Check', 'wp2-update' ) . '</a>'
            ]
        ];
    }
}
