<?php

declare(strict_types=1);

namespace Framework\Exceptions;

use RuntimeException;

/**
 * Any HTTP error the router or application wants to abort with.
 */
class HttpException extends RuntimeException
{
    public function __construct(
        private int $statusCode,
        string $message = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
