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
        ];
    }

    protected function get_bulk_actions() {
        return [
            'force-check' => __( 'Force update check', 'wp2-update' ),
            'clear-cache' => __( 'Clear package cache', 'wp2-update' ),
        ];
    }

    public function prepare_items() {
        $this->items = $this->fetch_packages();
    }

    private function fetch_packages() {
        // Fetch packages logic here
        return [];
    }

    public function process_bulk_action() {
        if ( 'force-check' === $this->current_action() ) {
            // Logic for forcing update checks
            foreach ($_POST['package'] as $package_id) {
                $this->utils->force_update_check($package_id);
            }
            echo '<div class="notice notice-success"><p>Update checks forced for selected packages.</p></div>';
        } elseif ( 'clear-cache' === $this->current_action() ) {
            // Logic for clearing package cache
            foreach ($_POST['package'] as $package_id) {
                $this->utils->clear_package_cache($package_id);
            }
            echo '<div class="notice notice-success"><p>Cache cleared for selected packages.</p></div>';
        }
    }
}