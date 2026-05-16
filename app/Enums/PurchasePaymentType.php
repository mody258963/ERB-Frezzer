<?php

namespace App\Enums;

enum PurchasePaymentType: string
{
    case Immediate = 'immediate';
    case Installments = 'installments';
}
