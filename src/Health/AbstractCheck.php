<?php
declare(strict_types=1);

namespace WP2\Update\Health;

/**
 * Abstract class for health checks, defining the contract for all checks.
 */
abstract class AbstractCheck {
    /**
     * The unique identifier for the check.
     * @var string
     */
    protected string $id;

    /**
     * The user-friendly label for the check.
     * @var string
     */
    protected string $label;

    /**
     * Run the health check and return its result.
     *
     * @return array An associative array containing the result.
     * Expected keys: 'label', 'status' ('pass', 'warn', 'error'), 'message'.
     */
    abstract public function run(): array;
}
