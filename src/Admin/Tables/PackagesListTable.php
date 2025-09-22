<?php

namespace WP2\Update\Admin\Tables;

use WP_List_Table;

class PackagesListTable extends AbstractListTable {
    private $connection;
    private $utils;

    public function __construct($connection, $utils) {
        $this->connection = $connection;
        $this->utils = $utils;
        parent::__construct([
            'singular' => 'package',
            'plural'   => 'packages',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'       => '<input type="checkbox" />',
            'name'     => __('Package Name', 'wp2-update'),
            'version'  => __('Version', 'wp2-update'),
            'status'   => __('Status', 'wp2-update'),
            'repo'     => __('Repository', 'wp2-update'), // Added column for GitHub repo links
        ];
    }

    protected function get_bulk_actions() {
        return [
            'force-check' => __( 'Force update check', 'wp2-update' ),
            'clear-cache' => __( 'Clear package cache', 'wp2-update' ),
        ];
    }

    public function prepare_items() {
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];

        $data = $this->fetch_packages();

        usort($data, function ($a, $b) {
            $orderby = $_GET['orderby'] ?? 'name';
            $order = $_GET['order'] ?? 'asc';

            $result = strcmp($a[$orderby], $b[$orderby]);

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

    private function fetch_packages() {
        // Fetch packages logic here
        return $this->connection->get_managed_packages();
    }

    public function process_bulk_action() {
        if ( 'force-check' === $this->current_action() ) {
            // Logic for forcing update checks
            $packages = isset($_POST['package']) ? array_map('sanitize_text_field', (array) $_POST['package']) : [];
            foreach ($packages as $package_id) {
                $this->utils->force_update_check($package_id);
            }
            echo '<div class="notice notice-success"><p>Update checks forced for selected packages.</p></div>';
        } elseif ( 'clear-cache' === $this->current_action() ) {
            // Logic for clearing package cache
            $packages = isset($_POST['package']) ? array_map('sanitize_text_field', (array) $_POST['package']) : [];
            foreach ($packages as $package_id) {
                $this->utils->clear_package_cache($package_id);
            }
            echo '<div class="notice notice-success"><p>Cache cleared for selected packages.</p></div>';
        }
    }

    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'repo':
                if (!empty($item['repo'])) {
                    return sprintf('<a href="%s" target="_blank">%s</a>', esc_url($item['repo']), esc_html__('View Repository', 'wp2-update'));
                }
                return esc_html__('N/A', 'wp2-update');
            default:
                return isset($item[$column_name]) ? esc_html($item[$column_name]) : '';
        }
    }
}