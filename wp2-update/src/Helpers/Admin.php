<?php

namespace WP2\Update\Helpers;

use WP2\Update\Utils\Log;

class Admin {
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function enqueue_scripts($hook) {
        // Only load on our plugin admin pages
        if (strpos($hook, 'wp2-update') === false) {
            return;
        }
        $js_path = dirname(__DIR__, 2) . '/assets/js/admin.js';
        $css_path = dirname(__DIR__, 2) . '/assets/css/admin.css';
        wp_enqueue_script(
            'wp2-updater-admin-js',
            plugins_url('../assets/js/admin.js', __DIR__),
            [],
            file_exists($js_path) ? filemtime($js_path) : null,
            true
        );
        wp_enqueue_style(
            'wp2-updater-admin-css',
            plugins_url('../assets/css/admin.css', __DIR__),
            [],
            file_exists($css_path) ? filemtime($css_path) : null
        );
        wp_localize_script('wp2-updater-admin-js', 'wp2_updater_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('wp2-ajax-nonce'),
        ]);
    }
    /**
     * Render the main admin page structure.
     */
    public static function render_page(string $page_title, string $type, array $managed_items, callable $health_check_renderer, callable $releases_renderer) {
        $current_tab = key($managed_items);
        if (isset($_GET['tab']) && array_key_exists($_GET['tab'], $managed_items)) {
            $current_tab = sanitize_key($_GET['tab']);
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($page_title); ?></h1>

            <?php if (empty($managed_items)) : ?>
                <div class="notice notice-warning">
                    <p><?php echo esc_html(sprintf(__("No managed %ss found. Please ensure your %s header contains an 'UpdateURI' in the format 'owner/repo'.", 'wp2-update'), $type, $type)); ?></p>
                </div>
                <?php return; endif; ?>

            <div class="wp2-update-toolbar" style="margin:12px 0 20px;">
                <button class="button wp2-force-check-button" data-type="<?php echo esc_attr($type); ?>">
                    <?php esc_html_e('Force Re-check All', 'wp2-update'); ?>
                </button>
                <p class="description"><?php echo esc_html(sprintf(__('Clears the %s update cache and triggers a fresh check against GitHub.', 'wp2-update'), $type)); ?></p>
            </div>
        <div id="wp2-release-modal" class="wp2-modal">
            <div class="wp2-modal-content">
                <span class="wp2-modal-close">&times;</span>
                <div id="wp2-modal-body"></div>
            </div>
        </div>

            <?php self::display_notices($type); ?>

            <h2 class="nav-tab-wrapper">
                <?php foreach ($managed_items as $key => $item) :
                    $update_data = get_site_transient('update_' . $type . 's'); // Assumes 'plugins', 'themes'
                    $has_update  = isset($update_data->response[$key]);
                    ?>
                    <a href="?page=wp2-update-dashboard&tab=<?php echo esc_attr($key); ?>" class="nav-tab <?php echo ($current_tab === $key) ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($item['name']); ?>
                        <?php if ($has_update) : ?>
                            <span style="color:#d63638;font-size:1.2em;vertical-align:middle;">&#9679;</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <?php
            $item_data = $managed_items[$current_tab];
            call_user_func($health_check_renderer, $item_data);
            call_user_func($releases_renderer, $item_data);
            self::display_logs();
            ?>
        </div>
        <?php
    }

 /**
     * Render a dashboard summary for all package types.
     * @param array $packages_by_type ['themes' => [...], 'plugins' => [...], 'daemons' => [...]]
     */
    public static function render_dashboard(array $packages_by_type) {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('WP2 Update Dashboard', 'wp2-update'); ?></h1>
            <p class="description"><?php esc_html_e('Overview of all managed packages and update status.', 'wp2-update'); ?></p>
            <form id="wp2-bulk-update-form" method="post" action="">
                <button type="button" class="button button-primary" id="wp2-bulk-update-btn" style="margin-bottom:10px;">
                    <?php esc_html_e('Bulk Update Selected', 'wp2-update'); ?>
                </button>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="wp2-bulk-select-all" /></th>
                            <th><?php esc_html_e('Type', 'wp2-update'); ?></th>
                            <th><?php esc_html_e('Name', 'wp2-update'); ?></th>
                            <th><?php esc_html_e('Current Version', 'wp2-update'); ?></th>
                            <th><?php esc_html_e('Update Available', 'wp2-update'); ?></th>
                            <th><?php esc_html_e('Actions', 'wp2-update'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($packages_by_type as $type => $items):
                        $update_data = get_site_transient('update_' . $type . 's');
                        foreach ($items as $key => $item):
                            $has_update = isset($update_data->response[$key]);
                            $current_version = $item['version'] ?? ($item['slug'] ?? $item['file'] ?? '');
                            $name = $item['name'] ?? $key;
                            ?>
                            <tr>
                                <td><input type="checkbox" class="wp2-bulk-select" name="wp2_bulk[]" value="<?php echo esc_attr($type . '|' . $key); ?>" /></td>
                                <td><?php echo esc_html(ucfirst($type)); ?></td>
                                <td><?php echo esc_html($name); ?></td>
                                <td><?php echo esc_html($current_version); ?></td>
                                <td><?php echo $has_update ? '<span style="color:#d63638;">&#9679; ' . esc_html($update_data->response[$key]['new_version'] ?? '') . '</span>' : '<span style="color:green;">&#10003;</span>'; ?></td>
                                <td>
                                    <a href="?page=wp2-update&tab=<?php echo esc_attr($key); ?>&type=<?php echo esc_attr($type); ?>" class="button">Explore</a>
                                    <?php if ($has_update): ?>
                                        <a href="?page=wp2-update&tab=<?php echo esc_attr($key); ?>&type=<?php echo esc_attr($type); ?>" class="button button-primary">Update</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endforeach; ?>
                    </tbody>
                </table>
            </form>
            <?php self::display_logs(); ?>
        </div>
        <?php
    }


    /**
     * Display admin notices for various actions and errors.
     */
    public static function display_notices(string $type) {
        if (isset($_GET['purged']) && (!isset($_GET['type']) || $_GET['type'] === $type)) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(__('%s update cache purged. A fresh check will run.', 'wp2-update'), ucfirst($type))) . '</p></div>';
        }
        if (isset($_GET['success'], $_GET['version']) && 'installed' === $_GET['success'] && (!isset($_GET['type']) || $_GET['type'] === $type)) {
            $version = sanitize_text_field(wp_unslash($_GET['version']));
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('%s successfully installed version %s.', 'wp2-update'), ucfirst($type), '<strong>' . esc_html($version) . '</strong>') . '</p></div>';
        }
        if (isset($_GET['error']) && (!isset($_GET['type']) || $_GET['type'] === $type)) {
            $error_message = get_transient('wp2_update_download_error') ?: __('An unknown error occurred.', 'wp2-update');
            delete_transient('wp2_update_download_error');
            echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__('Action Failed:', 'wp2-update') . '</strong> ' . esc_html($error_message) . '</p></div>';
        }
    }
    
    /**
     * Display recent log events.
     */
    public static function display_logs() {
        $logs = Log::get_logs();
        $filter_type = isset($_GET['log_type']) ? sanitize_text_field($_GET['log_type']) : '';
        $filter_context = isset($_GET['log_context']) ? sanitize_text_field($_GET['log_context']) : '';
        $filtered_logs = array_filter($logs, function($log) use ($filter_type, $filter_context) {
            $type_match = !$filter_type || stripos($log['message'], $filter_type) !== false;
            $context_match = !$filter_context || stripos($log['context'], $filter_context) !== false;
            return $type_match && $context_match;
        });
        ?>
        <h2><span class="dashicons dashicons-list-view"></span> <?php esc_html_e('Recent Events', 'wp2-update'); ?></h2>
        <form method="get" style="margin-bottom:10px;">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? 'wp2-update'); ?>" />
            <input type="text" name="log_type" placeholder="Type filter" value="<?php echo esc_attr($filter_type); ?>" />
            <input type="text" name="log_context" placeholder="Context filter" value="<?php echo esc_attr($filter_context); ?>" />
            <button class="button" type="submit">Filter</button>
            <button class="button" type="button" id="wp2-export-logs-csv">Export CSV</button>
            <button class="button" type="button" id="wp2-export-logs-json">Export JSON</button>
        </form>
        <div style="margin-bottom:10px;">
            <strong><?php esc_html_e('Total Events:', 'wp2-update'); ?></strong> <?php echo count($filtered_logs); ?> |
            <strong><?php esc_html_e('Errors:', 'wp2-update'); ?></strong> <?php echo count(array_filter($filtered_logs, fn($l)=>stripos($l['message'],'error')!==false)); ?> |
            <strong><?php esc_html_e('Updates:', 'wp2-update'); ?></strong> <?php echo count(array_filter($filtered_logs, fn($l)=>stripos($l['message'],'update')!==false)); ?>
        </div>
        <table class="widefat striped" id="wp2-logs-table">
            <thead>
                <tr>
                    <th style="width: 200px;"><?php esc_html_e('Timestamp', 'wp2-update'); ?></th>
                    <th><?php esc_html_e('Event', 'wp2-update'); ?></th>
                    <th><?php esc_html_e('Context', 'wp2-update'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($filtered_logs)) : ?>
                    <tr>
                        <td colspan="3"><?php esc_html_e('No events have been logged yet.', 'wp2-update'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($filtered_logs as $log) : ?>
                        <tr>
                            <td><?php echo esc_html(date('Y-m-d H:i:s', $log['timestamp'])); ?></td>
                            <td><?php echo esc_html($log['message']); ?></td>
                            <td><?php echo esc_html($log['context']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <script>
        jQuery(function($){
            function exportTable(format) {
                var rows = [];
                $('#wp2-logs-table tbody tr').each(function(){
                    var cols = $(this).find('td').map(function(){ return $(this).text(); }).get();
                    if(cols.length === 3) rows.push(cols);
                });
                if(format === 'csv') {
                    var csv = 'Timestamp,Event,Context\n' + rows.map(r=>r.join(',')).join('\n');
                    var blob = new Blob([csv], {type:'text/csv'});
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url; a.download = 'wp2-logs.csv'; a.click();
                } else if(format === 'json') {
                    var json = JSON.stringify(rows.map(r=>({timestamp:r[0],event:r[1],context:r[2]})),null,2);
                    var blob = new Blob([json], {type:'application/json'});
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url; a.download = 'wp2-logs.json'; a.click();
                }
            }
            $('#wp2-export-logs-csv').on('click', function(){ exportTable('csv'); });
            $('#wp2-export-logs-json').on('click', function(){ exportTable('json'); });
        });
        </script>
        <?php
    }

    public function render_logs_table() {
	$logs = Log::get_logs();
	$filter_type = $_GET['filter_type'] ?? '';
	$filter_context = $_GET['filter_context'] ?? '';
	$filtered_logs = array_filter($logs, function($log) use ($filter_type, $filter_context) {
		if ($filter_type && stripos($log['message'], $filter_type) === false) return false;
		if ($filter_context && stripos($log['context'], $filter_context) === false) return false;
		return true;
	});
	?>
	<div style="margin-bottom:10px;">
		<form method="get">
			<input type="hidden" name="page" value="wp2-update-dashboard" />
			<input type="text" name="filter_type" value="<?php echo esc_attr($filter_type); ?>" placeholder="Filter by type" />
			<input type="text" name="filter_context" value="<?php echo esc_attr($filter_context); ?>" placeholder="Filter by context" />
			<button type="submit" class="button">Filter</button>
			<button type="button" id="wp2-export-logs-csv" class="button">Export CSV</button>
			<button type="button" id="wp2-export-logs-json" class="button">Export JSON</button>
		</form>
		<div>
			<strong>Errors:</strong> <?php echo count(array_filter($filtered_logs, fn($l)=>stripos($l['message'],'error')!==false)); ?> |
			<strong>Updates:</strong> <?php echo count(array_filter($filtered_logs, fn($l)=>stripos($l['message'],'update')!==false)); ?>
		</div>
	</div>
	<table class="widefat striped" id="wp2-logs-table">
		<thead>
			<tr>
				<th style="width: 200px;">Timestamp</th>
				<th>Event</th>
				<th>Context</th>
			</tr>
		</thead>
		<tbody>
			<?php if (empty($filtered_logs)) : ?>
				<tr><td colspan="3">No events have been logged yet.</td></tr>
			<?php else : ?>
				<?php foreach ($filtered_logs as $log) : ?>
					<tr>
						<td><?php echo esc_html(date('Y-m-d H:i:s', $log['timestamp'])); ?></td>
						<td><?php echo esc_html($log['message']); ?></td>
						<td><?php echo esc_html($log['context']); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	<script>
	jQuery(function($){
		function exportTable(type){
			var rows = [];
			$('#wp2-logs-table tbody tr').each(function(){
				var cols = $(this).find('td').map(function(){ return $(this).text(); }).get();
				rows.push(cols);
			});
			if(type==='csv'){
				var csv = 'Timestamp,Event,Context\n' + rows.map(r=>r.join(',')).join('\n');
				var blob = new Blob([csv], {type:'text/csv'});
				var url = URL.createObjectURL(blob);
				var a = document.createElement('a');
				a.href = url;
				a.download = 'wp2-logs.csv';
				document.body.appendChild(a);
				a.click();
				document.body.removeChild(a);
			}else{
				var json = JSON.stringify(rows.map(r=>({timestamp:r[0],event:r[1],context:r[2]})),null,2);
				var blob = new Blob([json], {type:'application/json'});
				var url = URL.createObjectURL(blob);
				var a = document.createElement('a');
				a.href = url;
				a.download = 'wp2-logs.json';
				document.body.appendChild(a);
				a.click();
				document.body.removeChild(a);
			}
		}
		$('#wp2-export-logs-csv').on('click', function(){ exportTable('csv'); });
		$('#wp2-export-logs-json').on('click', function(){ exportTable('json'); });
	});
	</script>
	<?php
}

protected function render_package_table($items, $columns, $row_callback) {
	echo '<table class="widefat striped"><thead><tr>';
	foreach ($columns as $col) {
		echo '<th>' . esc_html($col) . '</th>';
	}
	echo '</tr></thead><tbody>';
	foreach ($items as $item) {
		call_user_func($row_callback, $item);
	}
	echo '</tbody></table>';
}
}

// TODO: Refactor to generalize admin layer for all package types and reduce duplication
// TODO: Add log filtering, export (CSV/JSON), and metrics to the admin UI