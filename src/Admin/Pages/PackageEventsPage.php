<?php
namespace WP2\Update\Admin\Pages;

use WP2\Update\Utils\Logger;

/**
 * Renders the "Event Log" tab content or a full page view.
 */
class PackageEventsPage {

    /**
     * Renders the tab content.
     */
    public function render() {
        $this->render_as_tab();
    }

    /**
     * Renders the content for a tab view.
     */
    public function render_as_tab() {
        $package_context = 'package_' . get_the_ID();
        $logs = Logger::get_logs_by_context($package_context);
        ?>
        <h2><?php esc_html_e( 'Recent Events For This Package', 'wp2-update' ); ?></h2>
        <p><?php esc_html_e('This is a global event log. Context-specific logs will be implemented in a future version.', 'wp2-update'); ?></p>
        <table class="wp2-data-table">
            <thead><tr><th><?php esc_html_e( 'Timestamp', 'wp2-update' ); ?></th><th><?php esc_html_e( 'Event', 'wp2-update' ); ?></th></tr></thead>
            <tbody>
                <?php if ( empty( $logs ) ) : ?>
                    <tr><td colspan="2"><?php esc_html_e( 'No events logged yet.', 'wp2-update' ); ?></td></tr>
                <?php else : foreach ( $logs as $log ) : ?>
                    <tr>
                        <td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) $log['timestamp'] ) ); ?></td>
                        <td><?php $this->render_log_message($log); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Renders the content as a full admin page.
     */
    public function render_as_view() {
        $logs = Logger::get_logs();
        ?>
        <div class="wrap wp2-update-page">
            <div class="wp2-update-header">
                <h1><?php esc_html_e('All Logged Events', 'wp2-update'); ?></h1>
                <p class="description"><?php esc_html_e('A log of all system events, from API calls to update checks.', 'wp2-update'); ?></p>
            </div>
            <div class="wp2-update-card">
                <table class="wp2-data-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Timestamp', 'wp2-update'); ?></th>
                            <th><?php esc_html_e('Type', 'wp2-update'); ?></th>
                            <th><?php esc_html_e('Context', 'wp2-update'); ?></th>
                            <th><?php esc_html_e('Message', 'wp2-update'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)) : ?>
                            <tr><td colspan="4"><?php esc_html_e('No events logged yet.', 'wp2-update'); ?></td></tr>
                        <?php else : foreach ($logs as $log) : ?>
                            <tr>
                                <td data-label="Timestamp"><?php echo esc_html(wp_date('Y-m-d H:i:s', (int) $log['timestamp'])); ?></td>
                                <td data-label="Type"><span class="wp2-log-type-<?php echo esc_attr($log['type']); ?>"><?php echo esc_html(ucfirst($log['type'])); ?></span></td>
                                <td data-label="Context"><?php echo esc_html(ucfirst($log['context'])); ?></td>
                                <td data-label="Message"><?php $this->render_log_message($log); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Renders a single log message, decoding JSON if necessary.
     */
    private function render_log_message(array $log) {
        $message_content = $log['message'] ?? '';
        $decoded_message = json_decode($message_content, true);

        if ( is_array($decoded_message) ) {
            echo '<pre>' . esc_html(print_r($decoded_message, true)) . '</pre>';
        } else {
            echo esc_html($message_content);
        }
    }
}
