<?php
namespace WP2\Update\Core\Connection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP2\Update\Core\Updates\PackageFinder;

/**
 * Connection facade for managed packages.
 */
final class Init {

	/** @var PackageFinder */
	private PackageFinder $package_finder;

	/**
	 * @param PackageFinder $package_finder
	 */
	public function __construct( PackageFinder $package_finder ) {
		$this->package_finder = $package_finder;
	}

	/**
	 * @return array<int,mixed>
	 */
	public function get_managed_plugins(): array {
		return $this->package_finder->get_managed_plugins();
	}

	/**
	 * @return array<int,mixed>
	 */
	public function get_managed_themes(): array {
		return $this->package_finder->get_managed_themes();
	}

	/**
	 * @return array<int,mixed>
	 */
	public function get_managed_packages(): array {
		return $this->package_finder->get_managed_packages();
	}

	/**
	 * Clears caches related to packages.
	 */
	public function clear_package_cache(): void {
		$this->package_finder->clear_cache();
	}
}

