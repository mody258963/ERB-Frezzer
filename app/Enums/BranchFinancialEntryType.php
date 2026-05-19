<?php

namespace App\Enums;

enum BranchFinancialEntryType: string
{
    case Charge = 'charge';
    case Payment = 'payment';
}
