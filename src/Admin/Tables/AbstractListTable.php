<?php

namespace WP2\Update\Admin\Tables;

use WP_List_Table;

abstract class AbstractListTable extends WP_List_Table {
    public function __construct($args = []) {
        parent::__construct($args);
    }

    public function prepare_items() {
        // Optionally, you can provide a default implementation or leave it empty.
    }

    abstract public function get_columns();
}