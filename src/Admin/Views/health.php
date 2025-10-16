<?php
/**
 * Renders the comprehensive system health status cards, designed for deep operational
 * and troubleshooting review. This view prominently displays the critical 'message'
 * field from each health check, alongside detailed REST API and localized data dumps.
 *
 * @var array $health_checks The health status data from HealthController (grouped by categories).
 * @var array $localized_data The localized data for debugging.
 * @var array $recent_logs The 10 most recent log entries.
 */

if (!defined('ABSPATH')) {
    exit;
}

use WP2\Update\Config;

// --------------------------------------------------------------------------------
// 1. Fallback & Helper Functions
// --------------------------------------------------------------------------------

$health_checks = $health_checks ?? [
    [
        'title' => __('System Checks (Data Unavailable)', Config::TEXT_DOMAIN),
        'checks' => [
            [
                'status' => 'error',
                'label' => 'Health Service Check',
                'message' => __('Health check data failed to load. Check PHP error logs for fatal errors in service initialization.', Config::TEXT_DOMAIN),
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
<div class="wp2-dashboard-root" id="wp2-update-health" role="status" aria-live="polite" data-bs-toggle="tooltip" data-bs-placement="top" title="This section provides a detailed overview of the system's health status, including checks for system integrity and integration.">
    <div class="row">
        <?php
        // Loop through main categories (System, Integrity, Integration)
        foreach ($health_checks as $category) : ?>
            <div class="col-lg-6">
                <div class="card mb-4 wp2-dashboard-card" data-bs-toggle="tooltip" data-bs-placement="top" title="Category: <?php echo esc_attr($category['title']); ?>">
                    <div class="card-body">
                        <h5 class="card-title text-uppercase font-weight-bold">
                            <?php echo esc_html($category['title']); ?>
                        </h5>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($category['checks'] as $check) : ?>
                                <li class="list-group-item p-3 border-bottom-0" data-bs-toggle="tooltip" data-bs-placement="top" title="Check: <?php echo esc_attr($check['label'] ?? 'Unknown Check'); ?>">
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
                    <h5 class="card-title"><?php esc_html_e('Client-Side Bootstrap Data (window.wp2UpdateData)', Config::TEXT_DOMAIN); ?></h5>
                    <p class="text-muted small"><?php esc_html_e('This is the exact JSON data localized by PHP to initialize the SPA state.', Config::TEXT_DOMAIN); ?></p>
                    
                    <div class="log-viewer wp2-sync-log" style="height: 300px; overflow-y: scroll; white-space: pre-wrap; font-size: 11px;">
                        <pre><?php echo esc_html(wp_json_encode($localized_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card wp2-dashboard-card">
                <div class="card-body">
                    <h5 class="card-title"><?php esc_html_e('Recent Log Entries (wp2_update_logs)', Config::TEXT_DOMAIN); ?></h5>
                    <p class="text-muted small">
                        <?php esc_html_e('These are the 10 most recent entries pulled directly from the database log table.', Config::TEXT_DOMAIN); ?>
                        <?php echo esc_html__('Last retrieved:', Config::TEXT_DOMAIN) . ' ' . current_time('mysql'); ?>
                    </p>
                    <div class="log-viewer wp2-sync-log" id="log-viewer" style="height: 300px; overflow-y: scroll;">
                        <?php
                        if (empty($recent_logs)) {
                            echo '<div class="log-entry text-muted small">', esc_html__('No logs recorded yet. Use the Sync button or initiate an update to generate logs.', Config::TEXT_DOMAIN), '</div>';
                        }
                        foreach (array_reverse($recent_logs) as $log) {
                            $context_data = json_decode($log['context'] ?? '[]', true);
                            $context_html = !empty($context_data) ? '<pre class="log-context">' . esc_html(print_r($context_data, true)) . '</pre>' : '';
                            $log_entry = sprintf('[%s] [%s] %s', $log['timestamp'], $log['level'], $log['message']);
                            echo '<div class="log-entry">', esc_html($log_entry), $context_html, '</div>';
                        }
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
                    <h5 class="card-title">Registered REST Endpoints</h5>
                    <p class="text-muted small">This table lists all REST API endpoints registered by the WP2 Update plugin.</p>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Route</th>
                                <th>Methods</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($health_checks['rest_endpoints']['data'] as $endpoint) : ?>
                                <tr>
                                    <td><?php echo esc_html($endpoint['route']); ?></td>
                                    <td><?php echo esc_html($endpoint['methods']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12 text-center">
            <button id="refresh-health-status" class="btn btn-primary">
                <?php esc_html_e('Refresh Health Status', Config::TEXT_DOMAIN); ?>
            </button>
        </div>
    </div>

</div>