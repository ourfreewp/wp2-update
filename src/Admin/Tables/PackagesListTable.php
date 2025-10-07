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
            'repo'     => __('Repository', 'wp2-update'),
            'update'   => __('Update', 'wp2-update'), // Added column for update actions
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
        $cache_key = 'wp2_merged_packages_data';
        $cached_data = get_transient($cache_key);
        if (is_array($cached_data)) {
            return $cached_data;
        }

        $themes = $this->connection->get_managed_themes();
        $plugins = $this->connection->get_managed_plugins();

        $theme_updates = get_site_transient('update_themes');
        $plugin_updates = get_site_transient('update_plugins');

        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_packages = [];

        foreach ($themes as $slug => $data) {
            $current_theme = wp_get_theme($slug);
            $installed_version = $current_theme->get('Version') ?: '0.0.0'; // Fallback to default version
            $latest_version = $theme_updates->response[$slug]['new_version'] ?? null;

            $all_packages[] = [
                'key' => 'theme:' . $slug,
                'slug' => $slug,
                'repo' => $data['repo'],
                'name' => $data['name'],
                'type' => 'theme',
                'installed_version' => $installed_version,
                'latest_version' => $latest_version,
                'update_available' => !empty($latest_version) && version_compare($latest_version, $installed_version, '>'),
            ];
        }

        foreach ($plugins as $slug => $data) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $slug);
            $installed_version = $plugin_data['Version'] ?? '0.0.0'; // Fallback to default version
            $latest_version = $plugin_updates->response[$slug]->new_version ?? null;

            $all_packages[] = [
                'key' => 'plugin:' . $slug,
                'slug' => $slug,
                'repo' => $data['repo'],
                'name' => $data['name'],
                'type' => 'plugin',
                'installed_version' => $installed_version,
                'latest_version' => $latest_version,
                'update_available' => !empty($latest_version) && version_compare($latest_version, $installed_version, '>'),
            ];
        }

        set_transient($cache_key, $all_packages, HOUR_IN_SECONDS);
        return $all_packages;
    }

    public function process_bulk_action() {
        // Delegate bulk action handling to the Controller
        do_action('wp2_handle_bulk_action', $this->current_action(), $_POST['packages'] ?? []);
    }

    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'repo':
                if (!empty($item['repo'])) {
                    return sprintf('<a href="%s" target="_blank">%s</a>', esc_url($item['repo']), esc_html__('View Repository', 'wp2-update'));
                }
                return esc_html__('N/A', 'wp2-update');
            case 'update':
                if (!empty($item['update_available'])) {
                    return sprintf(
                        '<button class="button button-primary" onclick="updatePackage(\'%s\')">%s</button>',
                        esc_attr($item['key']),
                        esc_html__('Update Now', 'wp2-update')
                    );
                }
                return esc_html__('Up-to-date', 'wp2-update');
            default:
                return isset($item[$column_name]) ? esc_html($item[$column_name]) : '';
        }
    }

    protected function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="packages[]" value="%s" />',
            esc_attr($item['key'])
        );
    }
}