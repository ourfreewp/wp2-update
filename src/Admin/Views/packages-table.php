<?php
/**
 * Packages Table View
 *
 * @var array $packages
 */
?>
<div class="wp2-table-wrapper">
    <table class="wp2-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Package Name', 'wp2-update'); ?></th>
                <th><?php esc_html_e('Version', 'wp2-update'); ?></th>
                <th><?php esc_html_e('Release', 'wp2-update'); ?></th>
                <th><?php esc_html_e('Actions', 'wp2-update'); ?></th>
                <th><?php esc_html_e('Auto Update', 'wp2-update'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($packages as $package): ?>
                <tr>
                    <td><?php echo esc_html($package['name'] ?? 'N/A'); ?></td>
                    <td><?php echo esc_html($package['version'] ?? 'N/A'); ?></td>
                    <td>
                        <select>
                            <?php foreach (($package['releases'] ?? []) as $release): ?>
                                <option value="<?php echo esc_attr($release['tag_name']); ?>" <?php selected($release['tag_name'], $package['version']); ?>>
                                    <?php echo esc_html($release['tag_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-action="update">
                            <?php esc_html_e('Update', 'wp2-update'); ?>
                        </button>
                        <button type="button" class="wp2-btn wp2-btn--ghost" data-wp2-action="view-release-notes" data-package-id="<?php echo esc_attr($package['id'] ?? ''); ?>">
                            <?php esc_html_e('View details', 'wp2-update'); ?>
                        </button>
                    </td>
                    <td>
                        <label class="wp2-switch">
                            <input type="checkbox" class="wp2-auto-update-toggle" data-package-id="<?php echo esc_attr($package['id'] ?? ''); ?>" <?php checked($package['auto_update'] ?? false); ?> />
                            <span class="wp2-slider"></span>
                        </label>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>