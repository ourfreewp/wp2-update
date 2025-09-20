<?php

namespace WP2\Update\Admin\Tables;

use WP_List_Table;

abstract class AbstractListTable extends WP_List_Table {
    public function __construct($args = []) {
        parent::__construct($args);
    }

    abstract public function prepare_items();

    abstract public function get_columns();
}