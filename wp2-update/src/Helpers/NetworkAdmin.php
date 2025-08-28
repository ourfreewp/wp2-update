<?php
namespace WP2\Update\Helpers;
use WP2\Update\Packages\Themes\Discovery as ThemeDiscovery;
use WP2\Update\Packages\Plugins\Discovery as PluginDiscovery;
if (!defined('ABSPATH')) exit;
class NetworkAdmin {
	public function render_network_page() {
		if (!is_multisite()) {
			echo '<div class="notice notice-warning"><p>Network admin is only available in multisite installations.</p></div>';
			return;
		}
		global $wpdb;
		$sites = get_sites(['limit' => 100]);
		echo '<div class="wrap"><h2>Network Overview</h2>';
		echo '<table class="widefat striped"><thead><tr><th>Site</th><th>Themes</th><th>Plugins</th><th>Daemons</th></tr></thead><tbody>';
		foreach ($sites as $site) {
			$details = get_blog_details($site->blog_id);
			$themes = get_blog_option($site->blog_id, 'stylesheet');
			$plugins = get_blog_option($site->blog_id, 'active_plugins');
			// Daemons: placeholder
			echo '<tr>';
			echo '<td><a href="' . esc_url($details->siteurl) . '" target="_blank">' . esc_html($details->blogname) . '</a></td>';
			echo '<td>' . esc_html($themes) . '</td>';
			echo '<td>' . (is_array($plugins) ? esc_html(implode(', ', $plugins)) : '-') . '</td>';
			echo '<td><em>Not implemented</em></td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}
}
// TODO: Integrate multisite/network admin dashboard tab and logic
