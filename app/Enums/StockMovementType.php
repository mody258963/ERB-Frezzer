<?php

namespace App\Enums;

enum StockMovementType: string
{
    case PurchaseIn = 'purchase_in';
    case SaleOut = 'sale_out';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case ReturnIn = 'return_in';
    case ReturnOut = 'return_out';
    case Adjustment = 'adjustment';
}
