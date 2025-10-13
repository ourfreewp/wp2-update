<?php
/**
 * Server-side fallback for the Dashboard View.
 * The primary dashboard is rendered by JavaScript. This provides a loading state or basic info.
 */

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="wp2-dashboard-view" role="region" aria-labelledby="dashboard-panel-title">
    <h2 id="dashboard-panel-title" class="screen-reader-text"><?php esc_html_e('Dashboard', \WP2\Update\Config::TEXT_DOMAIN); ?></h2>
    <div class="wp2-dashboard-loading">
        <div class="wp2-dashboard-spinner-lg"></div>
        <p class="wp2-muted"><?php esc_html_e('Loading Dashboard...', \WP2\Update\Config::TEXT_DOMAIN); ?></p>
    </div>
</div>
