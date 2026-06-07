<?php

namespace App\Transformers;

final class DashboardSummaryTransformer
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function transform(array $row): array
    {
        return [
            'total_receivables' => (float) ($row['total_receivables'] ?? 0),
            'total_supplier_debt' => (float) ($row['total_supplier_debt'] ?? 0),
            'total_stock_value_cost' => (float) ($row['total_stock_value_cost'] ?? 0),
            'business_capital' => (float) ($row['business_capital'] ?? 0),
            'capital_currency' => $row['capital_currency'] ?? 'EGP',
            'capital_estimated_available' => (float) ($row['capital_estimated_available'] ?? 0),
            'weekly_revenue' => (float) ($row['weekly_revenue'] ?? 0),
            'weekly_discount' => (float) ($row['weekly_discount'] ?? 0),
            'weekly_customer_refunds' => (float) ($row['weekly_customer_refunds'] ?? 0),
            'weekly_net_sales' => (float) ($row['weekly_net_sales'] ?? 0),
            'weekly_gross_profit' => (float) ($row['weekly_gross_profit'] ?? 0),
            'weekly_customer_refund_profit_impact' => (float) ($row['weekly_customer_refund_profit_impact'] ?? 0),
            'weekly_profit' => (float) ($row['weekly_profit'] ?? 0),
            'weekly_supplier_payments' => (float) ($row['weekly_supplier_payments'] ?? 0),
            'weekly_purchases_ordered' => (float) ($row['weekly_purchases_ordered'] ?? 0),
            'weekly_purchases_received' => (float) ($row['weekly_purchases_received'] ?? 0),
            'unpaid_installments_total' => (float) ($row['unpaid_installments_total'] ?? 0),
            'overdue_installments_total' => (float) ($row['overdue_installments_total'] ?? 0),
            'unpaid_installments_count' => (int) ($row['unpaid_installments_count'] ?? 0),
        ];
    }
}
