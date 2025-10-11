<?php

namespace WP2\Update\REST\Controllers;

/**
 * Contract for REST controllers managed by the plugin.
 */
interface RestControllerInterface {
	/**
	 * Register all REST routes for the controller.
	 */
	public function register_routes(): void;
}

