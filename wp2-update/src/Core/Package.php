<?php

namespace WP2\Update\Core;

/**
 * Interface for update package types (themes, plugins, daemons).
 */
interface Package {
    /**
     * Detect managed items for this package type.
     * @return array
     */
    public function detect(): array;

    /**
     * Hook update logic into WordPress.
     */
    public function hook_updates(): void;

    /**
     * Get the admin UI handler for this package type.
     * @return Admin|null
     */
    public function get_admin(): ?Admin;
}