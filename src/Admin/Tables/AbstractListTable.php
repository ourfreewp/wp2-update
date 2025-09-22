<?php

namespace WP2\Update\Admin\Tables;

// Ensure the core list table base class is available before extending it.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

use WP_List_Table;

abstract class AbstractListTable extends WP_List_Table {
    public function __construct($args = []) {
        parent::__construct($args);
    }

    public function prepare_items() {
        // Optionally, you can provide a default implementation or leave it empty.
    }

    public function get_columns() {
        // Provide a default implementation or leave it empty.
        return [];
    }
}
