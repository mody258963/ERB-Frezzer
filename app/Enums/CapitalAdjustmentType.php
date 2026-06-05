<?php

namespace App\Enums;

enum CapitalAdjustmentType: string
{
    case ManualSet = 'manual_set';
    case OwnerCashOut = 'owner_cash_out';
}
