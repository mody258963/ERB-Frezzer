<?php

namespace App\Rules;

use App\Services\CapitalService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MaxWithdrawableProfit implements ValidationRule
{
    public function __construct(
        private ?string $branchId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            return;
        }

        $snapshot = app(CapitalService::class)->profitWithdrawalSnapshot($this->branchId);
        $withdrawable = (string) $snapshot['withdrawable_profit'];

        if (bccomp((string) $value, $withdrawable, 2) > 0) {
            $fail(__('Cash out amount exceeds withdrawable profit (:available). Owner draws are deducted from profit margin, not business capital.', [
                'available' => number_format((float) $withdrawable, 2),
            ]));
        }
    }
}
