<?php
namespace WP2\Update\Admin\Pages;

use WP2\Update\Core\Utils\Logger;

/**
 * Renders the "Event Log" tab content.
 */
class PackageEventsPage {
    /**
     * Renders the tab content.
     */
    public function render() {
        $this->render_as_view();
    }

    /**
     * Renders the tab content as a view.
     */
    public function render_as_view() {
        $logs = Logger::get_logs();
        ?>
        <h2><?php esc_html_e( 'Recent Events', 'wp2-update' ); ?></h2>
        <table class="wp2-data-table">
            <thead><tr><th><?php esc_html_e( 'Timestamp', 'wp2-update' ); ?></th><th><?php esc_html_e( 'Event', 'wp2-update' ); ?></th></tr></thead>
            <tbody>
                <?php if ( empty( $logs ) ) : ?>
                    <tr><td colspan="2"><?php esc_html_e( 'No events logged yet.', 'wp2-update' ); ?></td></tr>
                <?php else : foreach ( $logs as $log ) : ?>
                    <tr>
                        <td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) $log['timestamp'] ) ); ?></td>
                        <td>
                            <?php 
                            if ( is_array( $log['message'] ) ) {
                                echo '<pre>' . esc_html( print_r( $log['message'], true ) ) . '</pre>';
                            } elseif ( is_object( $log['message'] ) ) {
                                echo '<pre>' . esc_html( json_encode( $log['message'], JSON_PRETTY_PRINT ) ) . '</pre>';
                            } elseif ( isset( $log['message'] ) && is_scalar( $log['message'] ) ) {
                                if ( isset( $log['context'] ) ) {
                                    if ( is_scalar( $log['context'] ) ) {
                                        $context = $log['context'];
                                    } elseif ( is_array( $log['context'] ) || is_object( $log['context'] ) ) {
                                        $context = json_encode( $log['context'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
                                    } else {
                                        $context = esc_html__( 'Unknown context', 'wp2-update' );
                                    }
                                } else {
                                    $context = esc_html__( 'Unknown context', 'wp2-update' );
                                }
                                echo esc_html( $log['message'] ) . ' (' . esc_html( $context ) . ')';
                            } else {
                                echo esc_html__( 'Invalid log message format.', 'wp2-update' );
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php
    }
}
