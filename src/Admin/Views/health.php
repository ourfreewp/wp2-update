<?php
/**
 * Renders the health status cards. This view is now primarily rendered server-side.
 *
 * @var array $health_checks The health status data from the HealthController.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper function to get a status icon based on the check result.
 * @param string $status The status ('pass', 'warn', 'error').
 * @return string The HTML for the status icon.
 */
function get_status_icon(string $status): string {
    if ('pass' === $status) {
        return '<span class="wp2-status-dot wp2-status-dot--ok"></span>';
    }
    if ('warn' === $status) {
        return '<span class="wp2-status-dot wp2-status-dot--update"></span>';
    }
    return '<span class="wp2-status-dot wp2-status-dot--error"></span>';
}

?>
<div class="wp2-health-view" role="region" aria-labelledby="health-panel-title">
    <h2 id="health-panel-title" class="screen-reader-text"><?php esc_html_e('System Health', \WP2\Update\Config::TEXT_DOMAIN); ?></h2>
    <?php if (empty($health_checks)) : ?>
        <p><?php esc_html_e('Could not retrieve health check information.', \WP2\Update\Config::TEXT_DOMAIN); ?></p>
    <?php else : ?>
        <?php foreach ($health_checks as $category) : ?>
            <?php if (!is_array($category) || empty($category['checks'])) continue; ?>
            <div class="wp2-dashboard-card" role="group" aria-labelledby="<?php echo esc_attr(sanitize_title($category['title'])); ?>">
                <h3 id="<?php echo esc_attr(sanitize_title($category['title'])); ?>"><?php echo esc_html($category['title']); ?></h3>
                <ul class="wp2-health-checks" role="list">
                    <?php foreach ($category['checks'] as $check) : ?>
                        <li class="wp2-health-check-item wp2-health-check--<?php echo esc_attr($check['status']); ?>" role="listitem">
                            <?php echo get_status_icon($check['status']); ?>
                            <span class="wp2-health-check-label"><?php echo esc_html($check['label']); ?></span>
                            <p class="wp2-health-check-message"><?php echo esc_html($check['message']); ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
