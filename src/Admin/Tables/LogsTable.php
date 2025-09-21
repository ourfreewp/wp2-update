<?php
namespace WP2\Update\Admin\Tables;

use WP_List_Table;

if (!class_exists('WP_List_Table')) {
    require_once WPINC . '/class-wp-list-table.php';
}

class LogsTable extends WP_List_Table {
    public function __construct() {
        parent::__construct([
            'singular' => __('Log', 'wp2-update'),
            'plural'   => __('Logs', 'wp2-update'),
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'timestamp' => __('Timestamp', 'wp2-update'),
            'type'      => __('Type', 'wp2-update'),
            'context'   => __('Context', 'wp2-update'),
            'message'   => __('Message', 'wp2-update'),
        ];
    }

    public function prepare_items() {
        $this->items = []; // Placeholder for log data
        $this->_column_headers = [$this->get_columns(), [], []];
    }

    public function column_default($item, $column_name) {
        return $item[$column_name] ?? '';
    }
}