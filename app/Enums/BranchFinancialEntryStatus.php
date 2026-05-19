<?php

namespace App\Enums;

enum BranchFinancialEntryStatus: string
{
    case Open = 'open';
    case Settled = 'settled';
}
