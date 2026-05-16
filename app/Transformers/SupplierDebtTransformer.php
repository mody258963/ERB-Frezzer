<?php

namespace App\Transformers;

use App\Models\Supplier;
use Illuminate\Support\Collection;

/**
 * @param  array{supplier: Supplier, purchase_orders?: Collection|\Illuminate\Database\Eloquent\Collection|array, installments?: Collection|\Illuminate\Database\Eloquent\Collection|array}  $payload
 */
final class SupplierDebtTransformer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function transform(array $payload): array
    {
        /** @var Supplier $supplier */
        $supplier = $payload['supplier'];

        return [
            'supplier' => SupplierTransformer::transform($supplier),
            'purchase_orders' => collect($payload['purchase_orders'] ?? [])
                ->map(fn ($po) => PurchaseOrderTransformer::transform($po))
                ->values()
                ->all(),
            'installments' => collect($payload['installments'] ?? [])
                ->map(fn ($i) => SupplierInstallmentSummaryTransformer::transform($i))
                ->values()
                ->all(),
        ];
    }
}
