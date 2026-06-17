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
            'period' => $row['period'] ?? null,
            'branch_id' => $row['branch_id'] ?? null,
            'total_receivables' => (float) ($row['total_receivables'] ?? 0),
            'total_supplier_debt' => (float) ($row['total_supplier_debt'] ?? 0),
            'total_stock_value_cost' => (float) ($row['total_stock_value_cost'] ?? 0),
            'business_capital' => (float) ($row['business_capital'] ?? 0),
            'capital_currency' => $row['capital_currency'] ?? 'EGP',
            'capital_estimated_available' => (float) ($row['capital_estimated_available'] ?? 0),
            'legacy_estimated_available' => (float) ($row['legacy_estimated_available'] ?? 0),
            'withdrawable_profit' => (float) ($row['withdrawable_profit'] ?? 0),
            'realized_profit' => (float) ($row['realized_profit'] ?? 0),
            'total_owner_cash_outs' => (float) ($row['total_owner_cash_outs'] ?? 0),
            'must_collect_customers' => (float) ($row['must_collect_customers'] ?? 0),
            'must_pay_suppliers' => (float) ($row['must_pay_suppliers'] ?? 0),
            'cash_on_hand_realized' => (float) ($row['cash_on_hand_realized'] ?? 0),
            'lifetime_cash_in_realized' => (float) ($row['lifetime_cash_in_realized'] ?? 0),
            'lifetime_cash_out_realized' => (float) ($row['lifetime_cash_out_realized'] ?? 0),
            'period_cash_in_realized' => (float) ($row['period_cash_in_realized'] ?? 0),
            'period_cash_out_realized' => (float) ($row['period_cash_out_realized'] ?? 0),
            'period_net_cash_flow_realized' => (float) ($row['period_net_cash_flow_realized'] ?? 0),
            'weekly_cash_in_realized' => (float) ($row['weekly_cash_in_realized'] ?? $row['period_cash_in_realized'] ?? 0),
            'weekly_cash_out_realized' => (float) ($row['weekly_cash_out_realized'] ?? $row['period_cash_out_realized'] ?? 0),
            'weekly_net_cash_flow_realized' => (float) ($row['weekly_net_cash_flow_realized'] ?? $row['period_net_cash_flow_realized'] ?? 0),
            'period_revenue' => (float) ($row['period_revenue'] ?? 0),
            'period_discount' => (float) ($row['period_discount'] ?? 0),
            'period_customer_refunds' => (float) ($row['period_customer_refunds'] ?? 0),
            'period_net_sales' => (float) ($row['period_net_sales'] ?? 0),
            'period_gross_profit' => (float) ($row['period_gross_profit'] ?? 0),
            'period_customer_refund_profit_impact' => (float) ($row['period_customer_refund_profit_impact'] ?? 0),
            'period_profit' => (float) ($row['period_profit'] ?? 0),
            'period_supplier_payments' => (float) ($row['period_supplier_payments'] ?? 0),
            'period_purchases_ordered' => (float) ($row['period_purchases_ordered'] ?? 0),
            'period_purchases_received' => (float) ($row['period_purchases_received'] ?? 0),
            'weekly_revenue' => (float) ($row['weekly_revenue'] ?? $row['period_revenue'] ?? 0),
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
