<?php
namespace WP2\Update\Admin\Pages;

use WP2\Update\Core\Connection\Init as Connection;

class BulkActionsPage {
    private $connection;
    
    public function __construct(Connection $connection) {
        $this->connection = $connection;
    }

    public function render() {
        $packages = $this->connection->get_managed_packages();
        ?>
        <div class="wrap wp2-update-page">
            <div class="wp2-update-header" style="margin-bottom: 1.5rem;">
                <h1><?php esc_html_e( 'Bulk Actions', 'wp2-update' ); ?></h1>
                <p class="description"><?php esc_html_e( 'Perform actions on multiple packages at once.', 'wp2-update' ); ?></p>
            </div>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="wp2_bulk_action">
                <?php wp_nonce_field( 'wp2_bulk_action', 'wp2_bulk_action_nonce' ); ?>
                
                <div class="wp2-update-card">
                     <div class="wp2-card-toolbar">
                        <div class="bulk-edit-actions">
                            <label for="bulk-action-selector" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'wp2-update' ); ?></label>
                            <select name="bulk-action" id="bulk-action-selector">
                                <option value="-1"><?php esc_html_e( 'Bulk Actions', 'wp2-update' ); ?></option>
                                <option value="force-check"><?php esc_html_e( 'Force update check', 'wp2-update' ); ?></option>
                                <option value="clear-cache"><?php esc_html_e( 'Clear package cache', 'wp2-update' ); ?></option>
                            </select>
                            <input type="submit" class="button action" value="<?php esc_attr_e( 'Apply', 'wp2-update' ); ?>">
                        </div>
                    </div>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <td id="cb" class="manage-column column-cb check-column">
                                    <label class="screen-reader-text" for="cb-select-all-1"><?php esc_html_e( 'Select All' ); ?></label>
                                    <input id="cb-select-all-1" type="checkbox">
                                </td>
                                <th><?php esc_html_e( 'Package Name', 'wp2-update' ); ?></th>
                                <th><?php esc_html_e( 'Type', 'wp2-update' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($packages)): ?>
                                <tr><td colspan="3"><?php esc_html_e('No managed packages found.', 'wp2-update'); ?></td></tr>
                            <?php else: foreach ($packages as $pkg): 
                                $key = $pkg['type'] . ':' . $pkg['slug'];    
                            ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="packages[]" value="<?php echo esc_attr($key); ?>">
                                    </th>
                                    <td><strong><?php echo esc_html($pkg['name']); ?></strong></td>
                                    <td><?php echo esc_html(ucfirst($pkg['type'])); ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
        <?php
    }
}
