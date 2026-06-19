<?php

namespace App\Transformers;

final class CapitalSettingsTransformer
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function transform(array $row): array
    {
        $snapshot = $row['financing_snapshot'] ?? [];
        $profitWithdrawal = $row['profit_withdrawal'] ?? [];

        return [
            'branch_id' => $row['branch_id'] ?? null,
            'branch' => $row['branch'] ?? null,
            'opening_cash_balance' => (float) ($row['opening_cash_balance'] ?? $row['capital_amount'] ?? 0),
            'capital_amount' => (float) ($row['capital_amount'] ?? $row['opening_cash_balance'] ?? 0),
            'business_capital' => (float) ($row['business_capital'] ?? 0),
            'currency' => $row['currency'] ?? 'EGP',
            'notes' => $row['notes'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'updated_by' => $row['updated_by'] ?? null,
            'financing_snapshot' => [
                'inventory_at_cost' => (float) ($snapshot['inventory_at_cost'] ?? 0),
                'cash_on_hand_realized' => (float) ($snapshot['cash_on_hand_realized'] ?? 0),
                'opening_cash_balance' => (float) ($snapshot['opening_cash_balance'] ?? $row['opening_cash_balance'] ?? 0),
                'business_capital' => (float) ($snapshot['business_capital'] ?? $row['business_capital'] ?? 0),
                'customer_receivables' => (float) ($snapshot['customer_receivables'] ?? 0),
                'supplier_debt' => (float) ($snapshot['supplier_debt'] ?? 0),
                'deployed_capital' => (float) ($snapshot['deployed_capital'] ?? 0),
                'estimated_available' => (float) ($snapshot['legacy_estimated_available'] ?? $snapshot['estimated_available'] ?? 0),
                'legacy_estimated_available' => (float) ($snapshot['legacy_estimated_available'] ?? $snapshot['estimated_available'] ?? 0),
            ],
            'profit_withdrawal' => [
                'realized_profit' => (float) ($profitWithdrawal['realized_profit'] ?? 0),
                'total_withdrawn' => (float) ($profitWithdrawal['total_withdrawn'] ?? 0),
                'withdrawable_profit' => (float) ($profitWithdrawal['withdrawable_profit'] ?? 0),
                'branch_id' => $profitWithdrawal['branch_id'] ?? null,
            ],
        ];
    }
}
