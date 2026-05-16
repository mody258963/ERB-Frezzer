<?php

namespace App\Enums;

enum StockTransferStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
