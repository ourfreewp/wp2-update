<?php
/**
 * Server-side fallback for the Apps Table View.
 * This content is primarily rendered by JavaScript but can be pre-rendered here.
 *
 * @var array $apps
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wp2-table-wrapper" role="region" aria-labelledby="apps-table-title">
    <h2 id="apps-table-title" class="screen-reader-text"><?php esc_html_e('GitHub Apps', \WP2\Update\Config::TEXT_DOMAIN); ?></h2>
    <table class="wp2-table" role="table">
        <thead>
            <tr role="row">
                <th role="columnheader"><?php esc_html_e('App Name', \WP2\Update\Config::TEXT_DOMAIN); ?></th>
                <th role="columnheader"><?php esc_html_e('Account Type', \WP2\Update\Config::TEXT_DOMAIN); ?></th>
                <th role="columnheader"><?php esc_html_e('Packages', \WP2\Update\Config::TEXT_DOMAIN); ?></th>
                <th role="columnheader"><?php esc_html_e('Actions', \WP2\Update\Config::TEXT_DOMAIN); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($apps)) : ?>
                <tr role="row">
                    <td colspan="4" class="wp2-empty-state"><?php esc_html_e('No GitHub Apps have been configured yet.', \WP2\Update\Config::TEXT_DOMAIN); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($apps as $app) : ?>
                    <tr role="row" data-app-id="<?php echo esc_attr($app['id'] ?? ''); ?>">
                        <td role="cell" data-wp2-label="<?php esc_attr_e('App Name', \WP2\Update\Config::TEXT_DOMAIN); ?>"><?php echo esc_html($app['name'] ?? __('Untitled App', \WP2\Update\Config::TEXT_DOMAIN)); ?></td>
                        <td role="cell" data-wp2-label="<?php esc_attr_e('Account Type', \WP2\Update\Config::TEXT_DOMAIN); ?>"><?php echo esc_html($app['account_type'] ?? 'user'); ?></td>
                        <td role="cell" data-wp2-label="<?php esc_attr_e('Packages', \WP2\Update\Config::TEXT_DOMAIN); ?>"><?php echo esc_html(count($app['packages'] ?? [])); ?></td>
                        <td role="cell" data-wp2-label="<?php esc_attr_e('Actions', \WP2\Update\Config::TEXT_DOMAIN); ?>">
                            <button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-action="app-details" aria-label="<?php echo sprintf(esc_attr__('View details for %s', \WP2\Update\Config::TEXT_DOMAIN), esc_html($app['name'] ?? 'Untitled App')); ?>">
                                <?php esc_html_e('View', \WP2\Update\Config::TEXT_DOMAIN); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
