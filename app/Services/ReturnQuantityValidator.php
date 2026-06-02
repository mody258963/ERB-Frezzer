<?php

namespace App\Services;

use App\Enums\InvoiceReturnStatus;
use App\Enums\ReturnReferenceType;
use App\Enums\ReturnStatus;
use App\Enums\ReturnType;
use App\Exceptions\ReturnQuantityExceededException;
use App\Models\Invoice;
use App\Models\ProductReturn;
use App\Models\PurchaseOrder;
use App\Models\ReturnItem;
use Illuminate\Support\Facades\DB;

class ReturnQuantityValidator
{
    /**
     * @param  list<array{part_id: string, quantity: int}>  $items
     */
    public function assertCustomerInvoiceReturn(string $invoiceId, array $items, ?string $excludeReturnId = null): void
    {
        $invoice = Invoice::query()->with('items')->find($invoiceId);
        if (! $invoice) {
            throw new \InvalidArgumentException('Invoice not found.');
        }

        if ($invoice->return_status === InvoiceReturnStatus::Returned) {
            throw new \InvalidArgumentException('This invoice is already fully returned.');
        }

        $soldByPart = $invoice->items
            ->groupBy('part_id')
            ->map(fn ($rows) => (int) $rows->sum('quantity'));

        $this->assertQuantitiesWithinReference(
            ReturnReferenceType::Invoice->value,
            $invoiceId,
            $items,
            $soldByPart->all(),
            $excludeReturnId,
        );
    }

    /**
     * @param  list<array{part_id: string, quantity: int}>  $items
     */
    public function assertSupplierPurchaseReturn(string $purchaseOrderId, array $items, ?string $excludeReturnId = null): void
    {
        $po = PurchaseOrder::query()->with('items')->find($purchaseOrderId);
        if (! $po) {
            throw new \InvalidArgumentException('Purchase order not found.');
        }

        if ($po->received_at === null) {
            throw new \InvalidArgumentException('Cannot return goods for a purchase that was not received.');
        }

        $receivedByPart = $po->items
            ->groupBy('part_id')
            ->map(fn ($rows) => (int) $rows->sum('quantity'));

        $this->assertQuantitiesWithinReference(
            ReturnReferenceType::PurchaseOrder->value,
            $purchaseOrderId,
            $items,
            $receivedByPart->all(),
            $excludeReturnId,
        );
    }

    /**
     * @param  array<string, int>  $soldByPart
     * @param  list<array{part_id: string, quantity: int}>  $items
     */
    private function assertQuantitiesWithinReference(
        string $referenceType,
        string $referenceId,
        array $items,
        array $soldByPart,
        ?string $excludeReturnId,
    ): void {
        $alreadyReturned = $this->returnedQuantitiesByPart($referenceType, $referenceId, $excludeReturnId);
        $failures = [];

        foreach ($items as $line) {
            $partId = $line['part_id'];
            $requested = (int) $line['quantity'];
            $sold = $soldByPart[$partId] ?? 0;
            $already = $alreadyReturned[$partId] ?? 0;
            $available = max(0, $sold - $already);

            if ($sold === 0) {
                $failures[] = [
                    'part_id' => $partId,
                    'requested' => $requested,
                    'sold' => 0,
                    'already_returned' => $already,
                    'available' => 0,
                ];

                continue;
            }

            if ($requested > $available) {
                $failures[] = [
                    'part_id' => $partId,
                    'requested' => $requested,
                    'sold' => $sold,
                    'already_returned' => $already,
                    'available' => $available,
                ];
            }
        }

        if ($failures !== []) {
            throw new ReturnQuantityExceededException(
                'Return quantity exceeds what is available on this document.',
                $failures,
            );
        }
    }

    /**
     * @return array<string, int>
     */
    public function returnedQuantitiesByPart(
        string $referenceType,
        string $referenceId,
        ?string $excludeReturnId = null,
    ): array {
        $query = ReturnItem::query()
            ->join('returns', 'returns.id', '=', 'return_items.return_id')
            ->where('returns.reference_type', $referenceType)
            ->where('returns.reference_id', $referenceId)
            ->whereIn('returns.status', [
                ReturnStatus::Pending->value,
                ReturnStatus::Completed->value,
            ]);

        if ($excludeReturnId !== null) {
            $query->where('returns.id', '!=', $excludeReturnId);
        }

        return $query
            ->select('return_items.part_id', DB::raw('SUM(return_items.quantity) as qty'))
            ->groupBy('return_items.part_id')
            ->pluck('qty', 'return_items.part_id')
            ->map(fn ($qty) => (int) $qty)
            ->all();
    }

    public function syncInvoiceReturnStatus(string $invoiceId): void
    {
        $invoice = Invoice::query()->with('items')->find($invoiceId);
        if (! $invoice) {
            return;
        }

        $soldByPart = $invoice->items
            ->groupBy('part_id')
            ->map(fn ($rows) => (int) $rows->sum('quantity'))
            ->all();

        if ($soldByPart === []) {
            return;
        }

        $returnedByPart = $this->returnedQuantitiesByPart(
            ReturnReferenceType::Invoice->value,
            $invoiceId,
        );

        $allFullyReturned = true;
        $anyReturned = false;

        foreach ($soldByPart as $partId => $soldQty) {
            $returnedQty = $returnedByPart[$partId] ?? 0;
            if ($returnedQty > 0) {
                $anyReturned = true;
            }
            if ($returnedQty < $soldQty) {
                $allFullyReturned = false;
            }
        }

        $status = InvoiceReturnStatus::None;
        if ($allFullyReturned && $anyReturned) {
            $status = InvoiceReturnStatus::Returned;
        } elseif ($anyReturned) {
            $status = InvoiceReturnStatus::Partial;
        }

        if ($invoice->return_status !== $status) {
            $invoice->return_status = $status;
            $invoice->save();
        }
    }
}
