<?php

namespace App\Transformers;

final class DashboardPayablesBySupplierTransformer
{
    /**
     * @param  list<array{supplier: \App\Models\Supplier, purchase_orders?: iterable, installments?: iterable}>  $rows
     * @return array{suppliers: list<array<string, mixed>>, total_supplier_debt: float}
     */
    public static function transform(array $rows): array
    {
        $suppliers = array_map(
            fn (array $row) => SupplierDebtTransformer::transform($row),
            $rows,
        );

        $totalDebt = array_reduce(
            $rows,
            fn (float $carry, array $row) => $carry + (float) $row['supplier']->total_debt,
            0.0,
        );

        return [
            'suppliers' => $suppliers,
            'total_supplier_debt' => round($totalDebt, 2),
        ];
    }
}
