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
<div class="card mt-4">
    <div class="card-body">
        <h2 id="packages-table-title" class="card-title">
            <?php esc_html_e('Packages', \WP2\Update\Config::TEXT_DOMAIN); ?>
        </h2>
        <div class="mb-3 text-end">
            <button id="wp2-refresh-packages" class="btn btn-secondary">
                <i class="bi bi-arrow-clockwise me-1"></i> <?php esc_html_e('Refresh', \WP2\Update\Config::TEXT_DOMAIN); ?>
            </button>
        </div>
        <table class="table table-hover" role="table">
            <thead>
                <tr role="row">
                    <th scope="col"><?php esc_html_e('Package', \WP2\Update\Config::TEXT_DOMAIN); ?></th>
                    <th scope="col"><?php esc_html_e('Installed Version', \WP2\Update\Config::TEXT_DOMAIN); ?></th>
                    <th scope="col"><?php esc_html_e('Status', \WP2\Update\Config::TEXT_DOMAIN); ?></th>
                    <th scope="col"><?php esc_html_e('Release Channel', \WP2\Update\Config::TEXT_DOMAIN); ?></th>
                    <th scope="col" class="text-end"><?php esc_html_e('Actions', \WP2\Update\Config::TEXT_DOMAIN); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($packages as $package) : ?>
                    <tr data-repo-slug="<?php echo esc_attr($package['repo']); ?>">
                        <td>
                            <strong><?php echo esc_html($package['name']); ?></strong>
                            <div class="text-muted small"><?php echo esc_html($package['repo']); ?></div>
                        </td>
                        <td><?php echo esc_html($package['version']); ?></td>
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
