<?php
// src/init.php

/**
 * The core update service orchestrator.
 *
 * @package WP2Updater
 */

namespace WP2\Update;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Init {
	private $package_admins = [];

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_wp2_update_menu' ] );
		add_action( 'after_setup_theme', function () {
			$packages = [ 
				'themes'  => new \WP2\Update\Packages\Themes\Init(),
				'plugins' => new \WP2\Update\Packages\Plugins\Init(),
				'daemons' => new \WP2\Update\Packages\Daemons\Init(),
			];

			foreach ( $packages as $slug => $pkg ) {
				$managed = $pkg->detect();
				$pkg->hook_updates();
				if ( is_admin() && $admin = $pkg->get_admin() ) {
					$admin->register( $managed );
					$this->package_admins[ $slug ] = $admin;
				}
			}

			\WP2\Update\Utils\Log::add( 'WP2 Update packages initialized.', 'info', 'general' );
		} );
	}

	public function add_wp2_update_menu() {
		add_menu_page(
			'WP2 Update',
			'WP2 Update',
			'manage_options',
			'wp2-update-dashboard',
			[ $this, 'render_dashboard' ],
			'dashicons-update',
			80
		);
	}

	public function render_dashboard() {
		$tabs        = [ 
			'themes'  => 'Themes',
			'plugins' => 'Plugins',
			'daemons' => 'Daemons',
			'network' => 'Network',
		];
		$current_tab = isset( $_GET['tab'] ) && isset( $tabs[ $_GET['tab'] ] ) ? $_GET['tab'] : 'themes';
		$focus       = isset( $_GET['focus'] ) ? $_GET['focus'] : '';
		echo '<div class="wrap"><h1>WP2 Update</h1><h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			$active = ( $current_tab === $slug ) ? ' nav-tab-active' : '';
			echo '<a href="?page=wp2-update-dashboard&tab=' . esc_attr( $slug ) . '" class="nav-tab' . $active . '">' . esc_html( $label ) . '</a>';
		}
		echo '</h2>';
		if ( $current_tab === 'network' ) {
			if ( class_exists( 'WP2\Update\Helpers\NetworkAdmin' ) ) {
				$networkAdmin = new \WP2\Update\Helpers\NetworkAdmin();
				$networkAdmin->render_network_page();
			} else {
				echo '<p>Network admin not available.</p>';
			}
		} elseif ( isset( $this->package_admins[ $current_tab ] ) ) {
			$admin = $this->package_admins[ $current_tab ];
			if ( method_exists( $admin, 'render_page' ) ) {
				$admin->render_page( $focus );
			}
		} else {
			echo '<p>No managed ' . esc_html( $tabs[ $current_tab ] ) . ' found.</p>';
		}
		echo '</div>';
	}
}