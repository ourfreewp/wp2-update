<?php
/**
 * Apps Table View
 *
 * @var array $apps
 */
?>
<div class="wp2-table-wrapper">
    <table class="wp2-table">
        <thead>
            <tr>
                <th><?php esc_html_e('App Name', 'wp2-update'); ?></th>
                <th><?php esc_html_e('Account Type', 'wp2-update'); ?></th>
                <th><?php esc_html_e('Packages', 'wp2-update'); ?></th>
                <th><?php esc_html_e('Actions', 'wp2-update'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($apps as $app): ?>
                <tr>
                    <td><?php echo esc_html($app['name'] ?? 'Untitled App'); ?></td>
                    <td><?php echo esc_html($app['account_type'] ?? 'user'); ?></td>
                    <td><?php echo esc_html(count($app['packages'] ?? [])); ?></td>
                    <td>
                        <button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-action="app-details" data-wp2-app="<?php echo esc_attr($app['id'] ?? ''); ?>">
                            <?php esc_html_e('View', 'wp2-update'); ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>