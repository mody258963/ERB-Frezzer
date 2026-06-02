<?php

namespace App\Transformers;

use App\Models\Invoice;
use App\Transformers\Concerns\TransformsBackedEnums;

final class InvoiceTransformer
{
    use TransformsBackedEnums;

    /**
     * @return array<string, mixed>
     */
    public static function transform(Invoice $invoice): array
    {
        $data = [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'customer_id' => $invoice->customer_id,
            'branch_id' => $invoice->branch_id,
            'payment_type' => self::enumValue($invoice->payment_type),
            'subtotal' => (float) $invoice->subtotal,
            'discount' => (float) $invoice->discount,
            'total' => (float) $invoice->total,
            'is_paid' => $invoice->is_paid,
            'paid_at' => $invoice->paid_at?->toISOString(),
            'return_status' => self::enumValue($invoice->return_status) ?? 'none',
            'settlement_id' => $invoice->settlement_id,
            'created_by' => $invoice->created_by,
            'created_at' => $invoice->created_at?->toISOString(),
            'updated_at' => $invoice->updated_at?->toISOString(),
        ];

        if ($invoice->relationLoaded('customer') && $invoice->customer) {
            $data['customer'] = CustomerTransformer::transform($invoice->customer);
        }

        if ($invoice->relationLoaded('branch') && $invoice->branch) {
            $data['branch'] = BranchTransformer::transform($invoice->branch);
        }

        if ($invoice->relationLoaded('creator') && $invoice->creator) {
            $data['creator'] = UserTransformer::transform($invoice->creator);
        }

        if ($invoice->relationLoaded('items')) {
            $data['items'] = $invoice->items
                ->map(fn ($item) => InvoiceItemTransformer::transform($item))
                ->values()
                ->all();
        }

        return $data;
    }
}
