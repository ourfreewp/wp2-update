<?php
namespace WP2\Update\Admin\Pages;

use WP2\Update\Utils\SharedUtils;

/**
 * Renders the Backup Management Page.
 */
class BackupManagementPage {
    private $utils;

    /**
     * Constructor.
     */
    public function __construct(SharedUtils $utils) {
        $this->utils = $utils;
    }

    /**
     * Renders the backup management page.
     */
    public function render() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Backup Management', 'wp2-update'); ?></h1>
            <p><?php esc_html_e('Manage your backups here.', 'wp2-update'); ?></p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Backup Name', 'wp2-update'); ?></th>
                        <th><?php esc_html_e('Date Created', 'wp2-update'); ?></th>
                        <th><?php esc_html_e('Actions', 'wp2-update'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Dynamic content will be loaded here -->
                </tbody>
            </table>
        </div>
        <?php
    }
}