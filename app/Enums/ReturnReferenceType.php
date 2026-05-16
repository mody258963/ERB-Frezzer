<?php

namespace App\Enums;

enum ReturnReferenceType: string
{
    case Invoice = 'invoice';
    case PurchaseOrder = 'purchase_order';
}
