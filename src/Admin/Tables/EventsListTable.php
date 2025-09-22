<?php

namespace WP2\Update\Admin\Tables;

class EventsListTable extends AbstractListTable {
    public function __construct() {
        parent::__construct([
            'singular' => 'event',
            'plural'   => 'events',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'       => '<input type="checkbox" />',
            'event'    => __('Event', 'wp2-update'),
            'date'     => __('Date', 'wp2-update'),
            'details'  => __('Details', 'wp2-update'),
        ];
    }

    public function prepare_items() {
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];

        $data = $this->fetch_events();

        usort($data, function ($a, $b) {
            $orderby = sanitize_text_field($_GET['orderby'] ?? 'event');
            $order = sanitize_text_field($_GET['order'] ?? 'asc');

            $a_val = isset($a[$orderby]) && is_string($a[$orderby]) ? $a[$orderby] : '';
            $b_val = isset($b[$orderby]) && is_string($b[$orderby]) ? $b[$orderby] : '';

            $result = strcmp($a_val, $b_val);

            return ('asc' === $order) ? $result : -$result;
        });

        $per_page = 10;
        $current_page = $this->get_pagenum();
        $total_items = count($data);

        $this->items = array_slice($data, (($current_page - 1) * $per_page), $per_page);
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
    }

    private function fetch_events() {
        // Fetch real events from the logger utility
        $logs = \WP2\Update\Utils\Logger::get_logs();

        // Transform logs into the required format for the table
        $events = [];
        foreach ($logs as $log) {
            $events[] = [
                'event'   => $log['event'] ?? ($log['context'] ?? 'Unknown Event'),
                'date'    => $log['date'] ?? (isset($log['timestamp']) ? date('Y-m-d H:i:s', $log['timestamp']) : 'Unknown Date'),
                'details' => $log['details'] ?? ($log['message'] ?? 'No Details'),
            ];
        }

        return $events;
    }

    public function column_default($item, $column_name) {
        // Replace debugging with actual data rendering
        return $item[$column_name] ?? 'N/A';
    }
}