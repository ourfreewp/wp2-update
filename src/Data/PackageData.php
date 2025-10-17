<?php

namespace WP2\Update\Data;

use WP2\Update\Services\PackageService;
use WP2\Update\Data\DTO\PackageDTO;
use WP2\Update\Config;

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
            return [
                'managed'  => [],
                'unlinked' => [],
                'all'      => [],
                'error'    => __('Unable to load package information at this time.', Config::TEXT_DOMAIN),
            ];
        }
    }

    /**
     * Retrieve paginated packages.
     *
     * @param int $page The current page number.
     * @param int $per_page The number of items per page.
     * @return PackageDTO[]
     */
    public function get_paginated_packages(int $page, int $per_page): array {
        $get_option = is_multisite() ? 'get_site_option' : 'get_option';
        $all_packages = $get_option(Config::OPTION_PACKAGES, []);

        $offset = ($page - 1) * $per_page;
        $paginated = array_slice($all_packages, $offset, $per_page);

        return array_map(fn($pkg) => PackageDTO::fromArray([
            ...$pkg,
            'repo_slug' => $pkg['repository'] ?? $pkg['repo_slug'] ?? '',
        ]), $paginated);
    }

    /**
     * Retrieve the PackageService instance.
     *
     * @return PackageService
     */
    public function get_service(): PackageService {
        return $this->packageService;
    }

    /**
     * Writes the package data to the WordPress options.
     *
     * @param PackageDTO[] $packages The package data to save.
     */
    public function persist(array $packages): void {
        $update_option = is_multisite() ? 'update_site_option' : 'update_option';
        $update_option(
            Config::OPTION_PACKAGES,
            array_map(fn(PackageDTO $pkg) => $pkg->toArray(), $packages),
            false
        );
    }
}
