<?php
/**
 * AJAX handler for WP2 Update plugin
 * @package WP2Updater
 */

namespace WP2\Update\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AJAX {
	public function __construct() {
		add_action( 'wp_ajax_wp2_force_check', [ $this, 'force_check' ] );
		add_action( 'wp_ajax_wp2_bulk_update', [ $this, 'bulk_update' ] );
	}

	public function bulk_update() {
		check_ajax_referer( 'wp2-ajax-nonce', 'nonce' );
		if ( ! current_user_can( 'install_plugins' ) && ! current_user_can( 'update_themes' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}
		$packages = isset( $_POST['packages'] ) ? json_decode( stripslashes( $_POST['packages'] ), true ) : [];
		if ( empty( $packages ) || ! is_array( $packages ) ) {
			wp_send_json_error( [ 'message' => 'No packages selected for update.' ] );
		}
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$results = [];
		foreach ( $packages as $pkg ) {
			$type   = sanitize_key( $pkg['type'] );
			$slug   = sanitize_text_field( $pkg['slug'] );
			$result = null;
			if ( $type === 'plugin' ) {
				$upgrader = new \Plugin_Upgrader();
				$result   = $upgrader->upgrade( $slug );
			} elseif ( $type === 'theme' ) {
				$upgrader = new \Theme_Upgrader();
				$result   = $upgrader->upgrade( $slug );
			}
			if ( is_wp_error( $result ) ) {
				$results[ $slug ] = $result->get_error_message();
			} else {
				$results[ $slug ] = 'Success';
			}
		}
		\WP2\Update\Utils\Log::add( 'Bulk update process completed.', 'info', 'bulk-update' );
		wp_send_json_success( [ 'results' => $results, 'message' => 'All updates attempted.' ] );
	}

	public function force_check() {
		check_ajax_referer( 'wp2-ajax-nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}
		$type = sanitize_key( $_POST['type'] ?? '' );
		if ( empty( $type ) || ! in_array( $type, [ 'theme', 'plugin', 'daemon' ] ) ) {
			wp_send_json_error( [ 'message' => 'Invalid package type specified.' ] );
		}
		switch ( $type ) {
			case 'theme':
				delete_site_transient( 'update_themes' );
				wp_update_themes();
				break;
			case 'plugin':
				delete_site_transient( 'update_plugins' );
				wp_update_plugins();
				break;
			case 'daemon':
				delete_site_transient( 'wp2_update_daemons' );
				// Custom daemon check logic if needed
				break;
		}
		\WP2\Update\Utils\Log::add( "AJAX force {$type} update check triggered by admin.", 'info', "{$type}-update" );
		wp_send_json_success( [ 'message' => ucfirst( $type ) . ' update check initiated.' ] );
	}
}
