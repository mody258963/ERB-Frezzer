<?php

namespace App\Enums;

enum ReturnResolution: string
{
    case Restock = 'restock';
    case Writeoff = 'writeoff';
    case Replace = 'replace';
    case RefundCash = 'refund_cash';
    case CreditNote = 'credit_note';
    case SupplierCredit = 'supplier_credit';
}
