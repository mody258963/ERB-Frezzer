<?php

namespace App\Transformers;

final class DashboardReceivableRowTransformer
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function transform(array $row): array
    {
        return [
            'customer_id' => $row['customer_id'] ?? null,
            'name' => $row['name'] ?? null,
            'outstanding_balance' => (float) ($row['outstanding_balance'] ?? 0),
            'unpaid_invoices' => (int) ($row['unpaid_invoices'] ?? 0),
        ];
    }
}
