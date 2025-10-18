<?php

declare(strict_types=1);

namespace WP2\Update\Data\DTO;

use WP2\Update\Utils\Logger;

/**
 * Data Transfer Object for package data.
 */
final class PackageDTO
{
    public string $name;
    public string $version;
    public string $repo_slug;
    public string $lastUpdated;
    public array $metadata;

    public function __construct(
        string $name,
        string $version,
        string $repo_slug,
        string $lastUpdated,
        array $metadata = []
    ) {
        $this->name = $name;
        $this->version = $version;
        $this->repo_slug = $repo_slug;
        $this->lastUpdated = $lastUpdated;
        $this->metadata = $metadata;
    }

    /**
     * Validate required fields in the data array.
     *
     * @param array $data The raw data to validate.
     * @throws \InvalidArgumentException If any required field is missing or invalid.
     */
    private static function validateData(array $data): void
    {
        foreach (['name', 'version', 'repo_slug', 'last_updated'] as $field) {
            if (empty($data[$field]) || !is_string($data[$field])) {
                Logger::error('PackageDTO validation failed.', ['missing_field' => $field, 'data' => $data]);
                throw new \InvalidArgumentException(sprintf('The field "%s" must be a non-empty string.', $field));
            }
        }
    }

    /**
     * Create a PackageDTO from an associative array.
     */
    public static function fromArray(array $data): self
    {
        self::validateData($data);

        \WP2\Update\Utils\Logger::assert( isset( $data['package_name'] ), 'PackageDTO missing required package_name', [ 'data' => $data ] );
        \WP2\Update\Utils\Logger::debug( 'PackageDTO initialized', [ 'data' => $data, 'file' => __FILE__, 'line' => __LINE__ ] );

        return new self(
            $data['name'],
            $data['version'],
            $data['repo_slug'],
            $data['last_updated'],
            $data['metadata'] ?? []
        );
    }

    /**
     * Prevent modification of properties after construction.
     */
    public function __set(string $name, $value): void
    {
        throw new \LogicException('Cannot modify immutable PackageDTO properties.');
    }

    /**
     * Convert the DTO to an associative array.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'repo_slug' => $this->repo_slug,
            'last_updated' => $this->lastUpdated,
            'package_name' => $this->name,
            'metadata' => $this->metadata,
        ];
    }
}
