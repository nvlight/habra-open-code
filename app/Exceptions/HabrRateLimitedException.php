<?php

namespace App\Exceptions;

use RuntimeException;

class HabrRateLimitedException extends RuntimeException
{
    public function __construct(
        string $message = 'Habr kek API rate limited',
        public readonly int $retryAfter = 30,
    ) {
        parent::__construct($message);
    }
}
