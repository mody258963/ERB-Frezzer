<?php

namespace App\Transformers;

final class FinancialReportTransformer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function transform(array $payload): array
    {
        $period = $payload['period'] ?? [];
        $totals = $payload['totals'] ?? [];
        $returns = $payload['returns'] ?? [];
        $capital = $payload['capital'] ?? [];
        $capitalSnapshot = $capital['financing_snapshot'] ?? [];
        $suppliers = $payload['suppliers'] ?? [];

        return [
            'period' => [
                'from' => $period['from'] ?? null,
                'to' => $period['to'] ?? null,
                'branch_id' => $period['branch_id'] ?? null,
            ],
            'totals' => [
                'revenue' => (float) ($totals['revenue'] ?? 0),
                'discount' => (float) ($totals['discount'] ?? 0),
                'customer_refunds' => (float) ($totals['customer_refunds'] ?? 0),
                'net_sales' => (float) ($totals['net_sales'] ?? 0),
                'gross_profit' => (float) ($totals['gross_profit'] ?? 0),
                'profit' => (float) ($totals['profit'] ?? 0),
            ],
            'returns' => [
                'customer_count' => (int) ($returns['customer_count'] ?? 0),
                'customer_value' => (float) ($returns['customer_value'] ?? 0),
                'supplier_count' => (int) ($returns['supplier_count'] ?? 0),
                'supplier_value' => (float) ($returns['supplier_value'] ?? 0),
            ],
            'capital' => [
                'capital_amount' => (float) ($capital['capital_amount'] ?? 0),
                'currency' => $capital['currency'] ?? 'EGP',
                'financing_snapshot' => [
                    'inventory_at_cost' => (float) ($capitalSnapshot['inventory_at_cost'] ?? 0),
                    'customer_receivables' => (float) ($capitalSnapshot['customer_receivables'] ?? 0),
                    'supplier_debt' => (float) ($capitalSnapshot['supplier_debt'] ?? 0),
                    'deployed_capital' => (float) ($capitalSnapshot['deployed_capital'] ?? 0),
                    'estimated_available' => (float) ($capitalSnapshot['estimated_available'] ?? 0),
                ],
            ],
            'suppliers' => [
                'total_debt' => (float) ($suppliers['total_debt'] ?? 0),
                'payments_in_period' => (float) ($suppliers['payments_in_period'] ?? 0),
                'purchases_ordered_in_period' => (float) ($suppliers['purchases_ordered_in_period'] ?? 0),
                'purchases_received_in_period' => (float) ($suppliers['purchases_received_in_period'] ?? 0),
                'unpaid_installments_total' => (float) ($suppliers['unpaid_installments_total'] ?? 0),
                'overdue_installments_total' => (float) ($suppliers['overdue_installments_total'] ?? 0),
            ],
            'by_branch' => collect($payload['by_branch'] ?? [])->map(fn ($row) => [
                'branch_id' => $row['branch_id'] ?? null,
                'name' => $row['name'] ?? null,
                'revenue' => (float) ($row['revenue'] ?? 0),
                'discount' => (float) ($row['discount'] ?? 0),
                'customer_refunds' => (float) ($row['customer_refunds'] ?? 0),
                'gross_profit' => (float) ($row['gross_profit'] ?? 0),
                'profit' => (float) ($row['profit'] ?? 0),
            ])->values()->all(),
        ];
    }
}
