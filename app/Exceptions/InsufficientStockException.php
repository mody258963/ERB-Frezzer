<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    /**
     * @param  list<array{part_id: string, requested: float|int|string, available: float|int|string}>  $failures
     */
    public function __construct(
        public readonly array $failures,
        string $message = 'Insufficient stock for one or more lines.'
    ) {
        parent::__construct($message);
    }
}
