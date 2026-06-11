<?php

namespace App\Services;

use App\Enums\CustomerType;
use App\Enums\InvoicePaymentType;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\Stock;
use App\Models\User;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Support\BranchAccess;
use App\Repositories\Contracts\StockMovementRepositoryInterface;
use App\Repositories\Contracts\StockRepositoryInterface;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private StockRepositoryInterface $stock,
        private StockMovementRepositoryInterface $movements,
        private WeightedAverageCostService $wac,
        private AuditLogService $audit,
        private DashboardCacheService $dashboardCache,
        private LowStockBroadcaster $lowStock,
    ) {}

    /**
     * @param  array{customer_id: string, branch_id: string, payment_type: string, discount?: float|int|string, items: list<array{part_id: string, quantity: int}>}  $data
     */
    public function create(User $user, array $data): Invoice
    {
        BranchAccess::assertUserMayUseBranch($user, $data['branch_id']);

        $invoice = DB::transaction(function () use ($user, $data) {
            $failures = [];

            foreach ($data['items'] as $line) {
                $stock = $this->stock->lockForPartAndBranch($line['part_id'], $data['branch_id']);
                $avail = $stock?->quantity ?? 0;
                if ($avail < $line['quantity']) {
                    $failures[] = [
                        'part_id' => $line['part_id'],
                        'requested' => $line['quantity'],
                        'available' => $avail,
                    ];
                }
            }

            if ($failures !== []) {
                throw new InsufficientStockException($failures);
            }

            $linesOut = [];
            $subtotal = '0';

            foreach ($data['items'] as $line) {
                $part = Part::query()->lockForUpdate()->findOrFail($line['part_id']);
                $unit = array_key_exists('unit_price', $line) && $line['unit_price'] !== null
                    ? (string) $line['unit_price']
                    : (string) $part->sell_price;
                $lineTotal = bcmul($unit, (string) $line['quantity'], 2);
                $subtotal = bcadd($subtotal, $lineTotal, 2);

                $stock = $this->stock->lockForPartAndBranch($line['part_id'], $data['branch_id']);
                $unitCost = $stock
                    ? $this->wac->snapshotCost($stock)
                    : (string) $part->cost_price;

                $linesOut[] = [
                    'part_id' => $part->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $unit,
                    'unit_cost' => $unitCost,
                    'total' => $lineTotal,
                    '_line' => $line,
                ];
            }

            $customer = Customer::query()->lockForUpdate()->findOrFail($data['customer_id']);
            if ($data['payment_type'] === InvoicePaymentType::Credit->value && $customer->type !== CustomerType::Credit) {
                throw new \InvalidArgumentException('Credit payment requires a credit customer.');
            }

            $discount = (string) ($data['discount'] ?? '0');
            $total = bcsub($subtotal, $discount, 2);

            if ($data['payment_type'] === InvoicePaymentType::Credit->value) {
                $outstanding = bcadd((string) $customer->outstanding_balance, $total, 2);
                if (bccomp($outstanding, (string) $customer->credit_limit, 2) > 0) {
                    throw new \InvalidArgumentException('Credit limit exceeded.');
                }
            }

            $isPaid = $data['payment_type'] === InvoicePaymentType::Cash->value;

            $invoice = $this->invoices->create(
                [
                    'invoice_number' => $this->invoices->nextInvoiceNumber(),
                    'customer_id' => $data['customer_id'],
                    'branch_id' => $data['branch_id'],
                    'payment_type' => $data['payment_type'],
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'total' => $total,
                    'is_paid' => $isPaid,
                    'paid_at' => $isPaid ? now() : null,
                    'settlement_id' => null,
                    'created_by' => $user->id,
                ],
                array_map(fn ($r) => [
                    'part_id' => $r['part_id'],
                    'quantity' => $r['quantity'],
                    'unit_price' => $r['unit_price'],
                    'unit_cost' => $r['unit_cost'],
                    'total' => $r['total'],
                ], $linesOut)
            );

            foreach ($linesOut as $row) {
                $line = $row['_line'];
                $stock = $this->stock->lockForPartAndBranch($line['part_id'], $data['branch_id']);
                if ($stock) {
                    $this->stock->adjustQuantity($stock, -1 * $line['quantity']);
                }
                $this->movements->create([
                    'part_id' => $line['part_id'],
                    'branch_id' => $data['branch_id'],
                    'movement_type' => StockMovementType::SaleOut,
                    'quantity' => -1 * $line['quantity'],
                    'reference_id' => $invoice->id,
                    'reference_type' => 'invoice',
                    'notes' => null,
                    'created_by' => $user->id,
                    'created_at' => now(),
                ]);
                $this->lowStock->notifyIfNeeded($line['part_id'], $data['branch_id']);
            }

            if ($data['payment_type'] === InvoicePaymentType::Credit->value) {
                $customer->outstanding_balance = bcadd((string) $customer->outstanding_balance, $total, 2);
                $customer->save();
            }

            $fresh = $this->invoices->findWithItems($invoice->id);
            $this->audit->record($user, 'invoice.create', 'invoice', $invoice->id, null, $fresh?->toArray());

            return $invoice->fresh(['items']);
        });

        $this->dashboardCache->forgetAllSummaries();

        return $invoice;
    }

    public function cancel(User $user, Invoice $invoice): void
    {
        if ($invoice->settlement_id) {
            throw new \InvalidArgumentException('Cannot cancel an invoice that was included in a settlement.');
        }

        $invoice->load('items');

        $before = $invoice->toArray();

        DB::transaction(function () use ($user, $invoice) {
            foreach ($invoice->items as $item) {
                $stock = $this->stock->firstOrCreate($item->part_id, $invoice->branch_id);
                $stock = Stock::query()->whereKey($stock->id)->lockForUpdate()->firstOrFail();
                $this->wac->applyInbound($stock, (int) $item->quantity, (string) $item->unit_cost);
                $this->movements->create([
                    'part_id' => $item->part_id,
                    'branch_id' => $invoice->branch_id,
                    'movement_type' => StockMovementType::Adjustment,
                    'quantity' => $item->quantity,
                    'reference_id' => $invoice->id,
                    'reference_type' => 'invoice_cancel',
                    'notes' => 'Invoice cancellation restore',
                    'created_by' => $user->id,
                    'created_at' => now(),
                ]);
            }

            if ($invoice->payment_type === InvoicePaymentType::Credit && ! $invoice->is_paid) {
                $customer = Customer::query()->lockForUpdate()->findOrFail($invoice->customer_id);
                $customer->outstanding_balance = bcsub((string) $customer->outstanding_balance, (string) $invoice->total, 2);
                $customer->save();
            }

            $invoice->delete();
        });

        $this->audit->record($user, 'invoice.cancel', 'invoice', $invoice->id, $before, null);
        $this->dashboardCache->forgetAllSummaries();
    }
}
