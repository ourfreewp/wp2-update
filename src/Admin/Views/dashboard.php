<?php
/**
 * Server-side fallback for the Dashboard View.
 * The primary dashboard is rendered by JavaScript. This provides a loading state or basic info.
 */

if (!defined('ABSPATH')) {
    exit;
}
use WP2\Update\Config;

$state = $this->data->get_state();
$updates_available_count = $state['packages']['updates_available_count'] ?? 0;
$unmanaged_count = $state['packages']['unmanaged_count'] ?? 0;
$health = $state['health'] ?? [];
$health_status = $health['overall_status'] ?? 'unknown';
$health_message = $health['overall_message'] ?? __('No health checks available.', Config::TEXT_DOMAIN);
$recent_logs = $this->data->get_recent_logs(5);
?>

<div class="wp2-dashboard-view" role="region" aria-labelledby="dashboard-panel-title">
    <h2 id="dashboard-panel-title" class="screen-reader-text"><?php esc_html_e('Dashboard', Config::TEXT_DOMAIN); ?></h2>

    <div class="row">
        <div class="col-md-6">
            <div class="card text-white bg-primary mb-3" data-bs-toggle="tooltip" data-bs-placement="top" title="This section shows the number of updates available for your managed packages.">
                <div class="card-body">
                    <h5 class="card-title"><?php esc_html_e('Updates Available', Config::TEXT_DOMAIN); ?></h5>
                    <p class="card-text fs-1"><?php echo esc_html($updates_available_count); ?></p>
                    <a href="<?php echo esc_url(add_query_arg('tab', 'packages')); ?>" class="text-white stretched-link"><?php esc_html_e('View Packages', Config::TEXT_DOMAIN); ?></a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card text-white bg-warning mb-3" data-bs-toggle="tooltip" data-bs-placement="top" title="This section highlights packages that are not yet managed. Assign them to ensure updates are tracked.">
                <div class="card-body">
                    <h5 class="card-title"><?php esc_html_e('Unmanaged Packages', Config::TEXT_DOMAIN); ?></h5>
                    <p class="card-text fs-1"><?php echo esc_html($unmanaged_count); ?></p>
                    <a href="<?php echo esc_url(add_query_arg('tab', 'packages')); ?>" class="text-white stretched-link"><?php esc_html_e('Assign Now', Config::TEXT_DOMAIN); ?></a>
                </div>
            </div>
        </div>
    </div>

    <div class="card my-4" data-bs-toggle="tooltip" data-bs-placement="top" title="Displays the overall system health status based on recent checks.">
        <div class="card-body">
            <h5 class="card-title"><?php esc_html_e('System Status', Config::TEXT_DOMAIN); ?></h5>
            <p class="card-text">
                <span class="badge bg-<?php echo $health_status === 'pass' ? 'success' : 'danger'; ?> me-2">
                    <?php echo $health_status === 'pass' ? '✅' : '⚠️'; ?>
                </span>
                <?php echo esc_html($health_message); ?>
                <a href="<?php echo esc_url(add_query_arg('tab', 'health')); ?>" class="ms-2"><?php esc_html_e('View Details', Config::TEXT_DOMAIN); ?></a>
            </p>
        </div>
    </div>

    <div class="card my-4" data-bs-toggle="tooltip" data-bs-placement="top" title="Shows the current status of the Magic Setup process.">
        <div class="card-body">
            <h5 class="card-title">Magic Setup Status</h5>
            <div id="magic-setup-status" class="alert alert-info" role="alert">
                <strong>Status:</strong> Waiting for callback...
            </div>
        </div>
    </div>
    
    <div class="card" data-bs-toggle="tooltip" data-bs-placement="top" title="Lists the most recent activities and logs for your packages and apps.">
        <div class="card-body">
            <h5 class="card-title"><?php esc_html_e('Recent Activity', Config::TEXT_DOMAIN); ?></h5>
            <ul class="list-group list-group-flush">
                <?php if (empty($recent_logs)) : ?>
                    <li class="list-group-item text-muted"><?php esc_html_e('No recent activity to display.', Config::TEXT_DOMAIN); ?></li>
                <?php else : ?>
                    <?php foreach ($recent_logs as $log) : ?>
                        <li class="list-group-item">
                            <span class="badge bg-info me-2"><?php echo esc_html($log['level']); ?></span>
                            <?php echo esc_html($log['message']); ?>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>