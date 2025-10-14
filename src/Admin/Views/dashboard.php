<?php
/**
 * Server-side fallback for the Dashboard View.
 * The primary dashboard is rendered by JavaScript. This provides a loading state or basic info.
 */

if (!defined('ABSPATH')) {
    exit;
}

$state = $this->data->get_state();
$updates_available_count = $state['packages']['updates_available_count'] ?? 0;
$unmanaged_count = $state['packages']['unmanaged_count'] ?? 0;
$health = $state['health'] ?? [];
$health_status = $health['overall_status'] ?? 'unknown';
$health_message = $health['overall_message'] ?? __('No health checks available.', \WP2\Update\Config::TEXT_DOMAIN);
$recent_logs = $this->data->get_recent_logs(5);
?>

<div class="wp2-dashboard-view" role="region" aria-labelledby="dashboard-panel-title">
    <h2 id="dashboard-panel-title" class="screen-reader-text"><?php esc_html_e('Dashboard', \WP2\Update\Config::TEXT_DOMAIN); ?></h2>

    <div class="row">
        <div class="col-md-6">
            <div class="card text-white bg-primary mb-3">
                <div class="card-body">
                    <h5 class="card-title"><?php esc_html_e('Updates Available', \WP2\Update\Config::TEXT_DOMAIN); ?></h5>
                    <p class="card-text fs-1"><?php echo esc_html($updates_available_count); ?></p>
                    <a href="<?php echo esc_url(add_query_arg('tab', 'packages')); ?>" class="text-white stretched-link"><?php esc_html_e('View Packages', \WP2\Update\Config::TEXT_DOMAIN); ?></a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card text-white bg-warning mb-3">
                <div class="card-body">
                    <h5 class="card-title"><?php esc_html_e('Unmanaged Packages', \WP2\Update\Config::TEXT_DOMAIN); ?></h5>
                    <p class="card-text fs-1"><?php echo esc_html($unmanaged_count); ?></p>
                    <a href="<?php echo esc_url(add_query_arg('tab', 'packages')); ?>" class="text-white stretched-link"><?php esc_html_e('Assign Now', \WP2\Update\Config::TEXT_DOMAIN); ?></a>
                </div>
            </div>
        </div>
    </div>

    <div class="card my-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="card-title"><?php esc_html_e('System Status', \WP2\Update\Config::TEXT_DOMAIN); ?></h5>
                    <p class="card-text">
                        <span class="badge bg-<?php echo $health_status === 'pass' ? 'success' : 'danger'; ?> me-2">
                            <?php echo $health_status === 'pass' ? '✅' : '⚠️'; ?>
                        </span>
                        <?php echo esc_html($health_message); ?>
                        <a href="<?php echo esc_url(add_query_arg('tab', 'health')); ?>" class="ms-2"><?php esc_html_e('View Details', \WP2\Update\Config::TEXT_DOMAIN); ?></a>
                    </p>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <button id="update-all-btn" class="btn btn-primary me-2">
                        <i class="bi bi-cloud-download me-1"></i> <?php esc_html_e('Update All', \WP2\Update\Config::TEXT_DOMAIN); ?>
                    </button>
                    <button id="sync-all-btn-dashboard" class="btn btn-secondary">
                        <i class="bi bi-arrow-repeat me-1"></i> <?php esc_html_e('Sync All Packages', \WP2\Update\Config::TEXT_DOMAIN); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="card-title"><?php esc_html_e('Recent Activity', \WP2\Update\Config::TEXT_DOMAIN); ?></h5>
            <ul class="list-group list-group-flush">
                <?php if (empty($recent_logs)) : ?>
                    <li class="list-group-item text-muted"><?php esc_html_e('No recent activity to display.', \WP2\Update\Config::TEXT_DOMAIN); ?></li>
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