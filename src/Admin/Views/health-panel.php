<?php
/**
 * Renders the health status cards.
 *
 * @var array $health_checks The health status data.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function get_status_icon( string $status ): string {
    if ( 'pass' === $status ) {
        return '<span class="wp2-status-dot wp2-status-dot--ok"></span>';
    }
    if ( 'warn' === $status ) {
        return '<span class="wp2-status-dot wp2-status-dot--update"></span>';
    }
    return '<span class="wp2-status-dot wp2-status-dot--error"></span>';
}

?>
<div class="wp2-health-view">
    <?php foreach ( $health_checks as $category ) : ?>
        <div class="wp2-dashboard-card">
            <h3><?php echo esc_html( $category['title'] ); ?></h3>
            <ul class="wp2-health-checks">
                <?php foreach ( $category['checks'] as $check ) : ?>
                    <li class="wp2-health-check-item wp2-health-check--<?php echo esc_attr( $check['status'] ); ?>">
                        <?php echo get_status_icon( $check['status'] ); ?>
                        <span class="wp2-health-check-label"><?php echo esc_html( $check['label'] ); ?></span>
                        <p class="wp2-health-check-message"><?php echo esc_html( $check['message'] ); ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>
</div>