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
        $backups = $this->fetch_backups();
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
                    <?php foreach ( $backups as $backup ) : ?>
                        <tr>
                            <td><?php echo esc_html( $backup['name'] ); ?></td>
                            <td><?php echo esc_html( $backup['date'] ); ?></td>
                            <td><?php echo esc_html( $backup['actions'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Fetches backup data.
     *
     * @return array
     */
    private function fetch_backups() {
        // Fetch backup data from the database or API.
        global $wpdb;
        $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wp2_backups", ARRAY_A);

        return array_map(function($row) {
            return [
                'name' => $row['backup_name'],
                'date' => $row['created_at'],
                'actions' => '<a href="#">Restore</a> | <a href="#">Delete</a>'
            ];
        }, $results);
    }
}