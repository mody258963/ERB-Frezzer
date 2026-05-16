<?php

namespace App\Enums;

enum SettlementPaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Check = 'check';
}
