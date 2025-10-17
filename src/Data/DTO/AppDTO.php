<?php

declare(strict_types=1);

namespace WP2\Update\Data\DTO;
use WP2\Update\Utils\Logger;

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
     * Maps snake_case keys to camelCase keys.
     *
     * @param array $data The raw data from the external API.
     * @return array The mapped data with camelCase keys.
     */
    private static function mapKeys(array $data): array
    {
        $mapping = [
            'installation_id' => 'installationId',
            'created_at' => 'createdAt',
            'updated_at' => 'updatedAt',
            'webhook_secret' => 'webhook_secret',
        ];

        foreach ($mapping as $snake => $camel) {
            if (isset($data[$snake])) {
                $data[$camel] = $data[$snake];
                unset($data[$snake]);
            }
        }

        return $data;
    }

    /**
     * Create an AppDTO from an associative array.
     */
    public static function fromArray(array $data): self
    {
        $data = self::mapKeys($data); // Map keys before validation

        // Validate required fields
        $requiredFields = ['id', 'createdAt', 'updatedAt', 'name', 'status', 'webhook_secret'];

        // Conditionally require installationId only if the app is considered installed
        if (isset($data['status']) && $data['status'] === 'installed') {
            $requiredFields[] = 'installationId';
        }

        foreach ($requiredFields as $field) {
            if (empty($data[$field]) || !is_string($data[$field])) {
                Logger::error('AppDTO validation failed.', ['missing_field' => $field, 'data' => $data]);
                throw new \InvalidArgumentException(sprintf('The field "%s" must be a non-empty string.', $field));
            }
        }

        // Ensure installationId is at least an empty string if not set
        $data['installationId'] = $data['installationId'] ?? '';

        \WP2\Update\Utils\Logger::assert( isset( $data['id'] ), 'AppDTO missing required id', [ 'data' => $data ] );
        \WP2\Update\Utils\Logger::debug( 'AppDTO initialized', [ 'data' => $data, 'file' => __FILE__, 'line' => __LINE__ ] );

        return new self(
            $data['id'],
            $data['installationId'],
            $data['createdAt'],
            $data['updatedAt'],
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