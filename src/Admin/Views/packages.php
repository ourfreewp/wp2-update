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

use WP2\Update\Config;

?>
<div class="card mt-4">
    <div class="card-body">
        <h2 id="packages-table-title" class="card-title">
            <?php esc_html_e('Packages', Config::TEXT_DOMAIN); ?>
        </h2>
        <div class="mb-3 d-flex justify-content-between align-items-center">
            <div>
                <select id="bulk-action-selector" class="form-select form-select-sm d-inline-block w-auto">
                    <option value="" selected><?php esc_html_e('Bulk Actions', Config::TEXT_DOMAIN); ?></option>
                    <option value="sync">Sync Selected</option>
                    <option value="update">Update Selected</option>
                    <option value="assign">Assign App to Selected</option>
                </select>
                <button id="apply-bulk-action" class="btn btn-primary btn-sm ms-2">
                    <?php esc_html_e('Apply', Config::TEXT_DOMAIN); ?>
                </button>
            </div>
            <button id="wp2-refresh-packages" class="btn btn-secondary">
                <i class="bi bi-arrow-clockwise me-1"></i> <?php esc_html_e('Refresh', Config::TEXT_DOMAIN); ?>
            </button>
        </div>
        <table class="table table-hover" role="table">
            <thead>
                <tr role="row">
                    <th scope="col">
                        <input type="checkbox" id="select-all-packages">
                    </th>
                    <th scope="col"><?php esc_html_e('Package', Config::TEXT_DOMAIN); ?></th>
                    <th scope="col"><?php esc_html_e('Installed Version', Config::TEXT_DOMAIN); ?></th>
                    <th scope="col"><?php esc_html_e('Status', Config::TEXT_DOMAIN); ?></th>
                    <th scope="col"><?php esc_html_e('Release Channel', Config::TEXT_DOMAIN); ?></th>
                    <th scope="col"><?php esc_html_e('Last Checked', Config::TEXT_DOMAIN); ?></th>
                    <th scope="col" class="text-end"><?php esc_html_e('Actions', Config::TEXT_DOMAIN); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($packages as $package) : ?>
                    <tr data-repo-slug="<?php echo esc_attr($package['repo']); ?>">
                        <td>
                            <input type="checkbox" class="package-checkbox" value="<?php echo esc_attr($package['repo']); ?>">
                        </td>
                        <td>
                            <strong><?php echo esc_html($package['name']); ?></strong>
                            <div class="text-muted small"><?php echo esc_html($package['repo']); ?></div>
                        </td>
                        <td>
                            <a href="#" class="release-notes-link" data-repo="<?php echo esc_attr($package['repo']); ?>">
                                <?php echo esc_html($package['version']); ?>
                            </a>
                        </td>
                        <td>
                            <?php echo esc_html($package['last_checked'] ?? 'N/A'); ?>
                        </td>
                        <td>
                            <span class="badge rounded-pill bg-<?php echo $package['status'] === 'Up to date' ? 'success' : 'warning'; ?>">
                                <?php echo esc_html($package['status']); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($package['release_channel']); ?></td>
                        <td class="text-end">
                            <div class="btn-group">
                                <button class="btn btn-primary btn-sm">Update</button>
                                <button class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"></button>
                                <ul class="dropdown-menu">
                                    <li><button class="dropdown-item">Rollback</button></li>
                                    <li><button class="dropdown-item">Assign</button></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>