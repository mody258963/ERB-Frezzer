<?php

namespace App\Support;

use App\Enums\CustomerType;
use App\Enums\SettlementCycle;
use App\Models\Customer;
use Illuminate\Support\Carbon;

final class CustomerSettlementSchedule
{
    public static function isDue(Customer $customer, float $amountDue): bool
    {
        if ($customer->type !== CustomerType::Credit || $amountDue <= 0) {
            return false;
        }

        $cycle = $customer->settlement_cycle ?? SettlementCycle::Weekly;
        $lastSettled = $customer->last_settled_at;

        if ($lastSettled === null) {
            return true;
        }

        return match ($cycle) {
            SettlementCycle::Daily => ! $lastSettled->isSameDay(now()),
            SettlementCycle::Weekly => $lastSettled->lt(now()->startOfWeek(Carbon::SATURDAY)),
        };
    }
}
