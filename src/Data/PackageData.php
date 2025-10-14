<?php

namespace WP2\Update\Data;

use WP2\Update\Services\PackageService;
use WP2\Update\Utils\Logger;

/**
 * Handles package-related data operations.
 */
class PackageData {
    private PackageService $packageService;

    public function __construct(PackageService $packageService) {
        $this->packageService = $packageService;
    }

    /**
     * Retrieve and group all packages (managed, unlinked, etc.).
     *
     * @return array<string,mixed>
     */
    public function get_all_packages_grouped(): array {
        try {
            return $this->packageService->get_all_packages_grouped();
        } catch (\Throwable $e) {
            Logger::log('ERROR', 'Failed to retrieve packages: ' . $e->getMessage());
            return [
                'managed'  => [],
                'unlinked' => [],
                'all'      => [],
                'error'    => __('Unable to load package information at this time.', \WP2\Update\Config::TEXT_DOMAIN),
            ];
        }
    }

    /**
     * Retrieve paginated packages.
     *
     * @param int $page The current page number.
     * @param int $per_page The number of items per page.
     * @return array<string,mixed>
     */
    public function get_paginated_packages(int $page, int $per_page): array {
        global $wpdb;

        $offset = ($page - 1) * $per_page;
        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wp2_packages LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );

        $results = $wpdb->get_results($query, ARRAY_A);

        if ($results === false) {
            Logger::log('ERROR', 'Failed to retrieve paginated packages.');
            return [];
        }

        return $results;
    }

    /**
     * Retrieve the PackageService instance.
     *
     * @return PackageService
     */
    public function get_service(): PackageService {
        return $this->packageService;
    }
}
