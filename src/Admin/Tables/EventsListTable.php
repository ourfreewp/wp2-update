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
        $this->items = $this->fetch_events();
    }

    private function fetch_events() {
        // Fetch events logic here
        return [
            [
                'event' => 'Package Updated',
                'date' => '2025-09-20',
                'details' => 'Updated to version 1.2.3',
            ],
            [
                'event' => 'Cache Cleared',
                'date' => '2025-09-19',
                'details' => 'Cleared cache for package XYZ',
            ],
        ];
    }
}