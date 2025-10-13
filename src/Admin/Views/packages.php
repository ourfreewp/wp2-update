<?php
/**
 * Server-side fallback for the Packages Table View.
 * This content is primarily rendered by JavaScript but can be pre-rendered here.
 *
 * @var array $packages
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wp2-table-wrapper" role="region" aria-labelledby="packages-table-title">
    <h2 id="packages-table-title" class="screen-reader-text"><?php esc_html_e('Packages', \WP2\Update\Config::TEXT_DOMAIN); ?></h2>
    <table class="wp2-table" role="table">
        <thead>
            <tr role="row">
                <th role="columnheader"><?php esc_html_e('Package Name', \WP2\Update\Config::TEXT_DOMAIN); ?></th>
                <th role="columnheader"><?php esc_html_e('Status', \WP2\Update\Config::TEXT_DOMAIN); ?></th>
                <th role="columnheader"><?php esc_html_e('Installed Version', \WP2\Update\Config::TEXT_DOMAIN); ?></th>
                <th role="columnheader"><?php esc_html_e('Latest Release', \WP2\Update\Config::TEXT_DOMAIN); ?></th>
                <th role="columnheader"><?php esc_html_e('Actions', \WP2\Update\Config::TEXT_DOMAIN); ?></th>
            </tr>
        </thead>
        <tbody>
             <?php if (empty($packages)) : ?>
                <tr role="row">
                    <td colspan="5" class="wp2-empty-state"><?php esc_html_e('No packages found. Check your plugin and theme headers for a valid "Update URI".', \WP2\Update\Config::TEXT_DOMAIN); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($packages as $package) : ?>
                    <tr role="row" data-repo-slug="<?php echo esc_attr($package['repo'] ?? ''); ?>">
                        <td role="cell" data-wp2-label="<?php esc_attr_e('Package Name', \WP2\Update\Config::TEXT_DOMAIN); ?>">
                            <div class="wp2-package-name"><?php echo esc_html($package['name'] ?? 'N/A'); ?></div>
                            <div class="wp2-package-repo"><?php echo esc_html($package['repo'] ?? 'N/A'); ?></div>
                        </td>
                        <td role="cell" data-wp2-label="<?php esc_attr_e('Status', \WP2\Update\Config::TEXT_DOMAIN); ?>"><?php echo esc_html($package['status'] ?? 'Unknown'); ?></td>
                        <td role="cell" data-wp2-label="<?php esc_attr_e('Installed Version', \WP2\Update\Config::TEXT_DOMAIN); ?>"><?php echo esc_html($package['version'] ?? 'N/A'); ?></td>
                        <td role="cell" data-wp2-label="<?php esc_attr_e('Latest Release', \WP2\Update\Config::TEXT_DOMAIN); ?>">
                            <?php echo esc_html($package['latest_label'] ?? 'N/A'); ?>
                        </td>
                        <td role="cell" class="wp2-table-actions" data-wp2-label="<?php esc_attr_e('Actions', \WP2\Update\Config::TEXT_DOMAIN); ?>">
                            <button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-action="view-details">
                                <?php esc_html_e('Details', \WP2\Update\Config::TEXT_DOMAIN); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
