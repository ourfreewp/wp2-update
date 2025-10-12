<?php

namespace WP2\Update\Core\Health;

/**
 * Abstract class for health checks.
 */
abstract class AbstractCheck {
    /**
     * Run the health check.
     *
     * @return array An associative array with 'status' and 'message'.
     */
    abstract public function run(): array;
}