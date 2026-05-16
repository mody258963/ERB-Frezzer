<?php

namespace App\Transformers;

use App\Models\ProductReturn;
use App\Transformers\Concerns\TransformsBackedEnums;

final class ProductReturnTransformer
{
    use TransformsBackedEnums;

    /**
     * @return array<string, mixed>
     */
    public static function transform(ProductReturn $productReturn): array
    {
        $data = [
            'id' => $productReturn->id,
            'return_number' => $productReturn->return_number,
            'return_type' => self::enumValue($productReturn->return_type),
            'reference_id' => $productReturn->reference_id,
            'reference_type' => self::enumValue($productReturn->reference_type),
            'customer_id' => $productReturn->customer_id,
            'supplier_id' => $productReturn->supplier_id,
            'branch_id' => $productReturn->branch_id,
            'reason' => $productReturn->reason,
            'status' => self::enumValue($productReturn->status),
            'resolution' => self::enumValue($productReturn->resolution),
            'total_value' => (float) $productReturn->total_value,
            'notes' => $productReturn->notes,
            'attachment_url' => $productReturn->attachment_url,
            'approved_by' => $productReturn->approved_by,
            'created_by' => $productReturn->created_by,
            'created_at' => $productReturn->created_at?->toISOString(),
            'updated_at' => $productReturn->updated_at?->toISOString(),
        ];

        if ($productReturn->relationLoaded('customer') && $productReturn->customer) {
            $data['customer'] = CustomerTransformer::transform($productReturn->customer);
        }

        if ($productReturn->relationLoaded('supplier') && $productReturn->supplier) {
            $data['supplier'] = SupplierTransformer::transform($productReturn->supplier);
        }

        if ($productReturn->relationLoaded('branch') && $productReturn->branch) {
            $data['branch'] = BranchTransformer::transform($productReturn->branch);
        }

        if ($productReturn->relationLoaded('creator') && $productReturn->creator) {
            $data['creator'] = UserTransformer::transform($productReturn->creator);
        }

        if ($productReturn->relationLoaded('approver') && $productReturn->approver) {
            $data['approver'] = UserTransformer::transform($productReturn->approver);
        }

        if ($productReturn->relationLoaded('items')) {
            $data['items'] = $productReturn->items
                ->map(fn ($item) => ReturnItemTransformer::transform($item))
                ->values()
                ->all();
        }

        return $data;
    }
}
