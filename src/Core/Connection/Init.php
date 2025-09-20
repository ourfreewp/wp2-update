<?php
namespace WP2\Update\Core\Connection;

use WP2\Update\Core\Updates\PackageFinder;

class Init {
    /** @var PackageFinder The package finder instance. */
    private PackageFinder $packageFinder;

    /**
     * Constructor.
     *
     * @param PackageFinder $packageFinder The package finder instance.
     */
    public function __construct(PackageFinder $packageFinder) {
        $this->packageFinder = $packageFinder;
    }

    /**
     * Retrieves the list of managed plugins.
     *
     * @return array The list of managed plugins.
     */
    public function get_managed_plugins(): array {
        return $this->packageFinder->get_managed_plugins();
    }

    /**
     * Retrieves the list of managed themes.
     *
     * @return array The list of managed themes.
     */
    public function get_managed_themes(): array {
        return $this->packageFinder->get_managed_themes();
    }

    /**
     * Retrieves the list of all managed packages (plugins and themes).
     *
     * @return array The list of managed packages.
     */
    public function get_managed_packages(): array {
        return $this->packageFinder->get_managed_packages();
    }

    /**
     * Clears all package-related caches.
     */
    public function clear_package_cache(): void {
        $this->packageFinder->clear_cache();
    }
}
