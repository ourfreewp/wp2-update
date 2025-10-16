<?php

declare(strict_types=1);

namespace WP2\Update\Data\DTO;

/**
 * Data Transfer Object for package data.
 */
final class PackageDTO
{
    public string $name;
    public string $version;
    public string $repository;
    public string $lastUpdated;
    public array $metadata;

    public function __construct(
        string $name,
        string $version,
        string $repository,
        string $lastUpdated,
        array $metadata = []
    ) {
        $this->name = $name;
        $this->version = $version;
        $this->repository = $repository;
        $this->lastUpdated = $lastUpdated;
        $this->metadata = $metadata;
    }

    /**
     * Create a PackageDTO from an associative array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['name'],
            $data['version'],
            $data['repository'],
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
            'repository' => $this->repository,
            'last_updated' => $this->lastUpdated,
            'metadata' => $this->metadata,
        ];
    }
}