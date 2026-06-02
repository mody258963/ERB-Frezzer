<?php

namespace App\Exceptions;

use Exception;

class ReturnQuantityExceededException extends Exception
{
    /**
     * @param  list<array{part_id: string, requested: int, sold: int, already_returned: int, available: int}>  $failures
     */
    public function __construct(
        string $message,
        public readonly array $failures,
    ) {
        parent::__construct($message);
    }
}
