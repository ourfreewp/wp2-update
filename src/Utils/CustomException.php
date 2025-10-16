<?php

declare(strict_types=1);

namespace WP2\Update\Utils;

use Exception;

/**
 * Custom exception class for WP2 Update plugin.
 */
class CustomException extends Exception {
    /**
     * HTTP status code associated with the exception.
     */
    private int $statusCode;

    /**
     * Constructor.
     *
     * @param string $message The exception message.
     * @param int $statusCode The HTTP status code (default: 500).
     */
    public function __construct(string $message, int $statusCode = 500) {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }

    /**
     * Gets the HTTP status code.
     *
     * @return int The HTTP status code.
     */
    public function getStatusCode(): int {
        return $this->statusCode;
    }
}