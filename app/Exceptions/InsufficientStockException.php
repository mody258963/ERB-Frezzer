<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    /**
     * @param  list<array{part_id: string, requested: int, available: int}>  $failures
     */
    public function __construct(
        public readonly array $failures,
        string $message = 'Insufficient stock for one or more lines.'
    ) {
        parent::__construct($message);
    }
}
