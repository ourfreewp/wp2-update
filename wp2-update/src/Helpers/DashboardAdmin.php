<?php
namespace WP2\Update\Helpers;

use WP2\Update\Helpers\Admin;
use WP2\Update\Packages\Themes\Discovery as ThemeDiscovery;
use WP2\Update\Packages\Plugins\Discovery as PluginDiscovery;
use WP2\Update\Packages\Daemons\Discovery as DaemonDiscovery;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DashboardAdmin {
    public function render_dashboard_page() {
        $themes  = ThemeDiscovery::detect();
        $plugins = PluginDiscovery::detect();
        $daemons = DaemonDiscovery::detect();
        $packages_by_type = [
            'theme'   => $themes,
            'plugin'  => $plugins,
            'daemon'  => $daemons,
        ];
        // Allow developers to filter/modify packages before rendering dashboard
        $packages_by_type = apply_filters('wp2_update_dashboard_packages', $packages_by_type);
        do_action('wp2_update_dashboard_pre_render', $packages_by_type);
        Admin::render_dashboard($packages_by_type);
        do_action('wp2_update_dashboard_post_render', $packages_by_type);
    }
}

// TODO: Implement async health checks via AJAX/REST for faster admin pages
// TODO: Ensure bulk actions work for all package types and provide UI feedback