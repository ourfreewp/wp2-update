<?php

namespace WP2\Update\Admin\Debug;

final class Manager {
    public static function render_debug_panel(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $user = wp_get_current_user();
        $debug = [
            'user'       => ['id' => $user->ID, 'login' => $user->user_login],
            'admin_url'  => admin_url(),
            'ajax_url'   => admin_url('admin-ajax.php'),
        ];
        ?>
        <div class="notice notice-info is-dismissible">
            <h2><?php echo esc_html__('WP2 Update Debug Panel', 'wp2-update'); ?></h2>
            <pre><?php echo esc_html(print_r($debug, true)); ?></pre>
        </div>
        <?php
    }
}