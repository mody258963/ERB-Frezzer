<?php

namespace App\Enums;

enum InvoicePaymentType: string
{
    case Credit = 'credit';
    case Cash = 'cash';
}
