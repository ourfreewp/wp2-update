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
}