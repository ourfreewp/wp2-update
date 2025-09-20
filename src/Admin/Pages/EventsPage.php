<?php
namespace WP2\Update\Admin\Views;

use WP2\Update\Core\Utils\Logger\Init as Logger;

/**
 * Renders the "Events" page content.
 */
class EventsPage {
    /**
     * Renders the page content.
     */
    public function render() {
        $logs = Logger::get_logs();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'All Logged Events', 'wp2-update' ); ?></h1>
            <table class="wp2-data-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Timestamp', 'wp2-update' ); ?></th>
                        <th><?php esc_html_e( 'Event', 'wp2-update' ); ?></th>
                        <th><?php esc_html_e( 'Origin/Source', 'wp2-update' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $logs ) ) : ?>
                        <tr><td colspan="3"><?php esc_html_e( 'No events logged yet.', 'wp2-update' ); ?></td></tr>
                    <?php else : foreach ( $logs as $log ) : ?>
                        <tr>
                            <td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) $log['timestamp'] ) ); ?></td>
                            <td>
                                <?php
                                $message = json_decode( $log['message'], true );
                                if ( json_last_error() === JSON_ERROR_NONE ) {
                                    echo '<pre>' . esc_html( print_r( $message, true ) ) . '</pre>';
                                } else {
                                    echo esc_html( $log['message'] );
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html( $log['origin'] ?? 'Unknown' ); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
