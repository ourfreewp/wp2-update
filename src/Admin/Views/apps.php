<?php
/**
 * Server-side fallback for the Apps Table View.
 * This content is primarily rendered by JavaScript but can be pre-rendered here.
 *
 * @var \WP2\Update\Services\Github\AppService $appService
 */

if (!defined('ABSPATH')) {
    exit;
}

use WP2\Update\Config;

// Fetch real app data dynamically from the backend or database.
$apps = $appService->get_apps();
?>
<div class="card mt-4">
    <div class="card-body">
        <h2 class="card-title">
            <?php esc_html_e('GitHub Apps', Config::TEXT_DOMAIN); ?>
        </h2>
        <table class="table table-hover" role="table">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('App Name', Config::TEXT_DOMAIN); ?></th>
                    <th scope="col"><?php esc_html_e('Account Type', Config::TEXT_DOMAIN); ?></th>
                    <th scope="col"><?php esc_html_e('Packages', Config::TEXT_DOMAIN); ?></th>
                    <th scope="col"><?php esc_html_e('Status', Config::TEXT_DOMAIN); ?></th>
                    <th scope="col" class="text-end"><?php esc_html_e('Actions', Config::TEXT_DOMAIN); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($apps)) : ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted">
                            <?php esc_html_e('No GitHub Apps available. Add a new app to get started.', Config::TEXT_DOMAIN); ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($apps as $app) : ?>
                        <tr data-app-id="<?php echo esc_attr($app->id); ?>">
                            <td>
                                <strong><?php echo esc_html($app->name); ?></strong>
                            </td>
                            <td><?php echo esc_html($app->account_type); ?></td>
                            <td>
                                <?php
                                // Ensure packages is an array before calling implode
                                echo esc_html(implode(', ', is_array($app->packages) ? $app->packages : []));
                                ?>
                            </td>
                            <td>
                                <?php
                                $status = $appService->test_connection($app->id) ? 'Connected' : 'Error';
                                $badge_class = $status === 'Connected' ? 'badge-success' : 'badge-danger';
                                ?>
                                <span class="badge <?php echo esc_attr($badge_class); ?>">
                                    <?php echo esc_html($status); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-primary btn-sm">View</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
