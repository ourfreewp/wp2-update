<?php
/**
 * Renders the comprehensive system health status cards, designed for deep operational
 * and troubleshooting review. This view prominently displays the critical 'message'
 * field from each health check, alongside detailed REST API and localized data dumps.
 *
 * @var array $health_checks The health status data from HealthController (grouped by categories).
 */

if (!defined('ABSPATH')) {
    exit;
}

// --------------------------------------------------------------------------------
// 1. Fallback & Helper Functions
// --------------------------------------------------------------------------------

$health_checks = $health_checks ?? [
    [
        'title' => __('System Checks (Data Unavailable)', \WP2\Update\Config::TEXT_DOMAIN),
        'checks' => [
            [
                'status' => 'error',
                'label' => 'Health Service Check',
                'message' => __('Health check data failed to load. Check PHP error logs for fatal errors in service initialization.', \WP2\Update\Config::TEXT_DOMAIN),
            ],
        ],
    ],
];

/**
 * Helper function to get a status icon based on the check result.
 */
function get_status_icon(string $status): string {
    if ('pass' === $status) {
        return '<span class="badge bg-success">✔</span>';
    }
    if ('warn' === $status) {
        return '<span class="badge bg-warning">⚠</span>';
    }
    return '<span class="badge bg-danger">✖</span>';
}

// --------------------------------------------------------------------------------
// 2. Robust View Rendering
// --------------------------------------------------------------------------------
?>
<div class="wp2-dashboard-root" id="wp2-update-health" role="status" aria-live="polite">
    <div class="row">
        <?php
        // Loop through main categories (System, Integrity, Integration)
        foreach ($health_checks as $category) : ?>
            <div class="col-lg-6">
                <div class="card mb-4 wp2-dashboard-card">
                    <div class="card-body">
                        <h5 class="card-title text-uppercase font-weight-bold">
                            <?php echo esc_html($category['title']); ?>
                        </h5>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($category['checks'] as $check) : ?>
                                <li class="list-group-item p-3 border-bottom-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <strong class="me-auto text-dark">
                                            <?php echo esc_html($check['label'] ?? 'Unknown Check'); ?>
                                        </strong>
                                        <span class="ms-3">
                                            <?php echo get_status_icon($check['status'] ?? 'error'); ?>
                                        </span>
                                    </div>
                                    
                                    <?php if (!empty($check['message'])) : ?>
                                        <p class="text-muted small mt-1 mb-0 font-italic">
                                            <?php echo esc_html($check['message']); ?>
                                        </p>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card wp2-dashboard-card">
                <div class="card-body">
                    <h5 class="card-title"><?php esc_html_e('Client-Side Bootstrap Data (window.wp2UpdateData)', \WP2\Update\Config::TEXT_DOMAIN); ?></h5>
                    <p class="text-muted small"><?php esc_html_e('This is the exact JSON data localized by PHP to initialize the SPA state.', \WP2\Update\Config::TEXT_DOMAIN); ?></p>
                    
                    <div class="log-viewer wp2-sync-log" style="height: 300px; overflow-y: scroll; white-space: pre-wrap; font-size: 11px;">
                        <?php 
                        // The localized data is only available when the script is enqueued.
                        // We rely on wp_localize_script outputting the variable 'wp2UpdateData'.
                        
                        $localized_data_key = 'wp2UpdateData';
                        
                        // WARNING: This is a hack for debugging. The real data structure is complex.
                        // The proper way is to use the AdminData service.
                        $admin_data = $this->container->get(\WP2\Update\Admin\Data::class);
                        $data_dump = $admin_data->get_state();
                        
                        echo '<pre>', esc_html(wp_json_encode($data_dump, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)), '</pre>';
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="row mt-4">
        <div class="col-12">
            <div class="card wp2-dashboard-card">
                <div class="card-body">
                    <h5 class="card-title"><?php esc_html_e('WP2 REST API Endpoints Debug', \WP2\Update\Config::TEXT_DOMAIN); ?></h5>
                    <p class="text-muted small">
                        <?php esc_html_e('Details on all registered endpoints. The "Callback" field confirms the code path for the API call.', \WP2\Update\Config::TEXT_DOMAIN); ?>
                    </p>
                    
                    <div class="wp2-table-wrapper mt-3">
                        <table class="wp2-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Endpoint', \WP2\Update\Config::TEXT_DOMAIN); ?></th>
                                    <th><?php esc_html_e('Methods', \WP2\Update\Config::TEXT_DOMAIN); ?></th>
                                    <th><?php esc_html_e('Action Nonce/Slug', \WP2\Update\Config::TEXT_DOMAIN); ?></th>
                                    <th><?php esc_html_e('Callback (Controller::Method)', \WP2\Update\Config::TEXT_DOMAIN); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // This manually replicates the crucial architectural mapping from the Router/Controllers.
                                $endpoints = [
                                    ['/apps', 'GET', 'wp2_list_apps', '\WP2\Update\REST\Controllers\AppsController::list_apps'],
                                    ['/apps', 'POST', 'wp2_create_app', '\WP2\Update\REST\Controllers\AppsController::create_app'],
                                    ['/packages', 'GET', 'wp2_get_packages', '\WP2\Update\REST\Controllers\PackagesController::get_packages'],
                                    ['/packages/sync', 'POST', 'wp2_sync_packages', '\WP2\Update\REST\Controllers\PackagesController::sync_packages'],
                                    ['/nonce', 'GET', 'wp2_get_nonce', '\WP2\Update\REST\Controllers\NonceController::get_nonce'],
                                    ['/webhook', 'POST', 'N/A', '\WP2\Update\Webhooks\WebhookController::handle'],
                                    ['/logs', 'GET', 'wp2_view_logs', '\WP2\Update\REST\Controllers\LogController::get_logs'],
                                    ['/credentials/generate-manifest', 'POST', 'wp2_generate_manifest', '\WP2\Update\REST\Controllers\CredentialsController::generate_manifest'],
                                ];
                                foreach ($endpoints as $endpoint) : ?>
                                    <tr>
                                        <td><code><?php echo esc_html(\WP2\Update\Config::REST_NAMESPACE . $endpoint[0]); ?></code></td>
                                        <td><?php echo esc_html($endpoint[1]); ?></td>
                                        <td><?php echo esc_html($endpoint[2]); ?></td>
                                        <td><code><?php echo esc_html($endpoint[3]); ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card wp2-dashboard-card">
                <div class="card-body">
                    <h5 class="card-title"><?php esc_html_e('Recent Log Entries (wp2_update_logs)', \WP2\Update\Config::TEXT_DOMAIN); ?></h5>
                    <p class="text-muted small">
                        <?php esc_html_e('These are the 10 most recent entries pulled directly from the database log table.', \WP2\Update\Config::TEXT_DOMAIN); ?>
                        <?php echo esc_html__('Last retrieved:', \WP2\Update\Config::TEXT_DOMAIN) . ' ' . current_time('mysql'); ?>
                    </p>
                    <div class="log-viewer wp2-sync-log" id="log-viewer" style="height: 300px; overflow-y: scroll;">
                        <?php
                        global $wpdb;
                        $table_name = $wpdb->prefix . \WP2\Update\Config::LOGS_TABLE_NAME;
                        
                        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
                        
                        if ($table_exists) {
                            $recent_logs = \WP2\Update\Utils\Logger::get_logs(10, 0); 
                            if (empty($recent_logs)) {
                                echo '<div class="log-entry text-muted small">', esc_html__('No logs recorded yet. Use the Sync button or initiate an update to generate logs.', \WP2\Update\Config::TEXT_DOMAIN), '</div>';
                            }
                            foreach (array_reverse($recent_logs) as $log) { 
                                $context_data = json_decode($log['context'] ?? '[]', true);
                                $context_html = !empty($context_data) ? '<pre class="log-context">' . esc_html(print_r($context_data, true)) . '</pre>' : '';
                                $log_entry = sprintf('[%s] [%s] %s', $log['timestamp'], $log['level'], $log['message']);
                                echo '<div class="log-entry">', esc_html($log_entry), $context_html, '</div>';
                            }
                        } else {
                             echo '<div class="log-entry text-danger">', esc_html__('CRITICAL: Log table is missing. Plugin activation failed. Check the Data Integrity check above and reactivate the plugin.', \WP2\Update\Config::TEXT_DOMAIN), '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>