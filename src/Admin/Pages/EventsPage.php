<?php
namespace WP2\Update\Admin\Pages;

use WP2\Update\Utils\Logger;
use WP2\Update\Admin\Tables\EventsListTable;

/**
 * Renders the "Events" page content.
 */
class EventsPage {
    /**
     * Renders the page content.
     */
    public function render() {
        $list_table = new EventsListTable();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'All Logged Events', 'wp2-update' ); ?></h1>
            <form method="post">
                <?php $list_table->display(); ?>
            </form>
        </div>
        <?php
    }
}
