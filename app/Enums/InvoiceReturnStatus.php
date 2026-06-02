<?php

namespace App\Enums;

enum InvoiceReturnStatus: string
{
    case None = 'none';
    case Partial = 'partial';
    case Returned = 'returned';
}
