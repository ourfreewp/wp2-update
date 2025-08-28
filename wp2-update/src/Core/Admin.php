<?php
namespace WP2\Update\Core;

/**
 * Interface for admin UI handlers for package types.
 */
interface Admin {
	/**
	 * Register managed packages for the admin UI.
	 * @param array $managed
	 * @return void
	 */
	public function register(array $managed): void;

	/**
	 * Render the admin page for this package type.
	 * @return void
	 */
	public function render_page(): void;
}
