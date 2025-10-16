<?php

declare(strict_types=1);

namespace WP2\Update\Data\DTO;

/**
 * Data Transfer Object for GitHub App connection data.
 */
final class AppDTO
{
    public string $id;
    public string $installationId;
    public string $createdAt;
    public string $updatedAt;
    public string $name;
    public string $status;
    public string $webhook_secret;
    public array $metadata;

    public function __construct(
        string $id,
        string $installationId,
        string $createdAt,
        string $updatedAt,
        string $name,
        string $status,
        string $webhook_secret,
        array $metadata = []
    ) {
        $this->id = $id;
        $this->installationId = $installationId;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->name = $name;
        $this->status = $status;
        $this->webhook_secret = $webhook_secret;
        $this->metadata = $metadata;
    }

    /**
     * Create an AppDTO from an associative array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            $data['installation_id'],
            $data['created_at'],
            $data['updated_at'],
            $data['name'],
            $data['status'],
            $data['webhook_secret'],
            $data['metadata'] ?? []
        );
    }

    /**
     * Prevent modification of properties after construction.
     */
    public function __set(string $name, $value): void
    {
        throw new \LogicException('Cannot modify immutable AppDTO properties.');
    }

    /**
     * Convert the DTO to an associative array.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'installationId' => $this->installationId,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'name' => $this->name,
            'status' => $this->status,
            'webhook_secret' => $this->webhook_secret,
            'metadata' => $this->metadata,
        ];
    }
}