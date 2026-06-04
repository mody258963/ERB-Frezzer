<?php

namespace App\Services;

use App\Enums\ReturnReferenceType;
use App\Enums\ReturnStatus;
use App\Models\Invoice;
use App\Models\ProductReturn;
use App\Models\ReturnItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceReturnContextService
{
    /**
     * Per-part return breakdown for an invoice (for UI + receipt).
     *
     * @return array<string, array{
     *     quantity_sold: int,
     *     quantity_returned_completed: int,
     *     quantity_returned_pending: int,
     *     quantity_available: int
     * }>
     */
    public function quantitiesByPart(Invoice $invoice): array
    {
        $invoice->loadMissing('items');

        $soldByPart = $invoice->items
            ->groupBy('part_id')
            ->map(fn ($rows) => (int) $rows->sum('quantity'))
            ->all();

        $completed = $this->returnedQuantitiesByPartForStatuses(
            $invoice->id,
            [ReturnStatus::Completed->value],
        );
        $pending = $this->returnedQuantitiesByPartForStatuses(
            $invoice->id,
            [ReturnStatus::Pending->value],
        );

        $result = [];
        foreach ($soldByPart as $partId => $sold) {
            $completedQty = $completed[$partId] ?? 0;
            $pendingQty = $pending[$partId] ?? 0;
            $reserved = $completedQty + $pendingQty;
            $result[$partId] = [
                'quantity_sold' => $sold,
                'quantity_returned_completed' => $completedQty,
                'quantity_returned_pending' => $pendingQty,
                'quantity_available' => max(0, $sold - $reserved),
            ];
        }

        return $result;
    }

    /**
     * @return Collection<int, ProductReturn>
     */
    public function returnsForInvoice(string $invoiceId): Collection
    {
        return ProductReturn::query()
            ->with(['items.part', 'creator', 'approver'])
            ->where('reference_type', ReturnReferenceType::Invoice->value)
            ->where('reference_id', $invoiceId)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Payload for Flutter print / PDF (original invoice + return lines + totals).
     *
     * @return array<string, mixed>
     */
    public function receiptPayload(Invoice $invoice): array
    {
        $invoice->loadMissing(['items.part', 'customer', 'branch', 'creator']);
        $byPart = $this->quantitiesByPart($invoice);
        $returns = $this->returnsForInvoice($invoice->id);

        $items = $invoice->items->map(function ($item) use ($byPart) {
            $stats = $byPart[$item->part_id] ?? [
                'quantity_sold' => (int) $item->quantity,
                'quantity_returned_completed' => 0,
                'quantity_returned_pending' => 0,
                'quantity_available' => (int) $item->quantity,
            ];

            return [
                'id' => $item->id,
                'part_id' => $item->part_id,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->total,
                'quantity_sold' => $stats['quantity_sold'],
                'quantity_returned_completed' => $stats['quantity_returned_completed'],
                'quantity_returned_pending' => $stats['quantity_returned_pending'],
                'quantity_remaining' => max(
                    0,
                    $stats['quantity_sold']
                        - $stats['quantity_returned_completed']
                        - $stats['quantity_returned_pending']
                ),
                'quantity_available_for_return' => $stats['quantity_available'],
                'part' => $item->relationLoaded('part') && $item->part
                    ? [
                        'code' => $item->part->code,
                        'name' => $item->part->name,
                    ]
                    : null,
            ];
        })->values()->all();

        $returnedCompletedValue = $this->sumReturnValue($returns, [ReturnStatus::Completed->value]);
        $returnedPendingValue = $this->sumReturnValue($returns, [ReturnStatus::Pending->value]);

        return [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer_id' => $invoice->customer_id,
                'branch_id' => $invoice->branch_id,
                'payment_type' => $invoice->payment_type?->value,
                'subtotal' => (float) $invoice->subtotal,
                'discount' => (float) $invoice->discount,
                'total' => (float) $invoice->total,
                'return_status' => $invoice->return_status?->value ?? 'none',
                'is_paid' => $invoice->is_paid,
                'created_at' => $invoice->created_at?->toISOString(),
                'customer' => $invoice->customer ? [
                    'id' => $invoice->customer->id,
                    'name' => $invoice->customer->name,
                ] : null,
                'branch' => $invoice->branch ? [
                    'id' => $invoice->branch->id,
                    'name' => $invoice->branch->name,
                ] : null,
            ],
            'items' => $items,
            'returns' => $returns->map(fn (ProductReturn $r) => [
                'id' => $r->id,
                'return_number' => $r->return_number,
                'status' => $r->status?->value,
                'resolution' => $r->resolution?->value,
                'total_value' => (float) $r->total_value,
                'created_at' => $r->created_at?->toISOString(),
                'items' => $r->items->map(fn ($ri) => [
                    'part_id' => $ri->part_id,
                    'quantity' => (int) $ri->quantity,
                    'unit_price' => (float) $ri->unit_price,
                    'condition' => $ri->condition?->value,
                    'part_code' => $ri->relationLoaded('part') && $ri->part ? $ri->part->code : null,
                    'part_name' => $ri->relationLoaded('part') && $ri->part ? $ri->part->name : null,
                ])->values()->all(),
            ])->values()->all(),
            'summary' => [
                'original_subtotal' => (float) $invoice->subtotal,
                'original_discount' => (float) $invoice->discount,
                'original_total' => (float) $invoice->total,
                'returned_value_completed' => $returnedCompletedValue,
                'returned_value_pending' => $returnedPendingValue,
                'net_total_after_completed_returns' => max(
                    0,
                    (float) bcsub((string) $invoice->total, (string) $returnedCompletedValue, 2)
                ),
            ],
        ];
    }

    /**
     * @param  list<string>  $statuses
     * @return array<string, int>
     */
    private function returnedQuantitiesByPartForStatuses(string $invoiceId, array $statuses): array
    {
        return ReturnItem::query()
            ->join('returns', 'returns.id', '=', 'return_items.return_id')
            ->where('returns.reference_type', ReturnReferenceType::Invoice->value)
            ->where('returns.reference_id', $invoiceId)
            ->whereIn('returns.status', $statuses)
            ->select('return_items.part_id', DB::raw('SUM(return_items.quantity) as qty'))
            ->groupBy('return_items.part_id')
            ->pluck('qty', 'return_items.part_id')
            ->map(fn ($qty) => (int) $qty)
            ->all();
    }

    /**
     * @param  Collection<int, ProductReturn>  $returns
     * @param  list<string>  $statuses
     */
    private function sumReturnValue(Collection $returns, array $statuses): float
    {
        return (float) $returns
            ->filter(fn (ProductReturn $r) => in_array($r->status?->value, $statuses, true))
            ->sum('total_value');
    }
}
