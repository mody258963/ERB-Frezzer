<?php

namespace App\Services;

use App\Enums\InvoiceReturnStatus;
use App\Enums\ReturnReferenceType;
use App\Enums\ReturnStatus;
use App\Exceptions\ReturnQuantityExceededException;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\ReturnItem;
use Illuminate\Support\Facades\DB;

class ReturnQuantityValidator
{
    /**
     * @param  list<array{part_id: string, quantity: float|int|string}>  $items
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
            ->map(fn ($rows) => (string) $rows->sum('quantity'))
            ->all();

        $this->assertQuantitiesWithinReference(
            ReturnReferenceType::Invoice->value,
            $invoiceId,
            $items,
            $soldByPart,
            $excludeReturnId,
        );
    }

    /**
     * @param  list<array{part_id: string, quantity: float|int|string}>  $items
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
            ->map(fn ($rows) => (string) $rows->sum('quantity'))
            ->all();

        $this->assertQuantitiesWithinReference(
            ReturnReferenceType::PurchaseOrder->value,
            $purchaseOrderId,
            $items,
            $receivedByPart,
            $excludeReturnId,
        );
    }

    /**
     * @param  array<string, string>  $soldByPart
     * @param  list<array{part_id: string, quantity: float|int|string}>  $items
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
            $requested = (string) $line['quantity'];
            $sold = $soldByPart[$partId] ?? '0';
            $already = $alreadyReturned[$partId] ?? '0';
            $available = bccomp($sold, $already, 4) > 0
                ? bcsub($sold, $already, 4)
                : '0';

            if (bccomp($sold, '0', 4) <= 0) {
                $failures[] = [
                    'part_id' => $partId,
                    'requested' => (float) $requested,
                    'sold' => 0,
                    'already_returned' => (float) $already,
                    'available' => 0,
                ];

                continue;
            }

            if (bccomp($requested, $available, 4) > 0) {
                $failures[] = [
                    'part_id' => $partId,
                    'requested' => (float) $requested,
                    'sold' => (float) $sold,
                    'already_returned' => (float) $already,
                    'available' => (float) $available,
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
     * @return array<string, string>
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
            ->map(fn ($qty) => (string) $qty)
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
            ->map(fn ($rows) => (string) $rows->sum('quantity'))
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
            $returnedQty = $returnedByPart[$partId] ?? '0';
            if (bccomp($returnedQty, '0', 4) > 0) {
                $anyReturned = true;
            }
            if (bccomp($returnedQty, $soldQty, 4) < 0) {
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
