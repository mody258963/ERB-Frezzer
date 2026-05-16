<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Settled = 'settled';
    case Cancelled = 'cancelled';
}
