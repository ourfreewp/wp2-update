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
     * Constructor to initialize the unique identifier for the health check.
     *
     * @param string $id The unique identifier for the health check.
     */
    public function __construct(string $id) {
        $this->id = $id;
    }

    /**
     * Run the health check and return its result.
     *
     * @return array An associative array containing the result.
     * Expected keys: 'label', 'status' ('pass', 'warn', 'error'), 'message'.
     */
    abstract public function run(): array;

    /**
     * Returns the unique identifier for the health check.
     *
     * @return string The unique identifier.
     */
    public function getName(): string {
        return $this->id;
    }
}
