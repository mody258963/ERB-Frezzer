<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Enums\StockTransferStatus;
use App\Models\Stock;
use App\Models\StockTransfer;
use App\Models\User;
use App\Repositories\Contracts\StockMovementRepositoryInterface;
use App\Repositories\Contracts\StockRepositoryInterface;
use App\Repositories\Contracts\StockTransferRepositoryInterface;
use Illuminate\Support\Facades\DB;

class StockTransferService
{
    public function __construct(
        private StockTransferRepositoryInterface $transfers,
        private StockRepositoryInterface $stock,
        private StockMovementRepositoryInterface $movements,
        private WeightedAverageCostService $wac,
        private BranchFinanceService $branchFinance,
        private AuditLogService $audit,
        private DashboardCacheService $dashboardCache,
        private LowStockBroadcaster $lowStock,
    ) {}

    public function complete(User $user, StockTransfer $transfer, string $valuation = 'cost', bool $recordBranchCharge = true): StockTransfer
    {
        if ($transfer->status !== StockTransferStatus::Pending) {
            throw new \InvalidArgumentException('Transfer is not pending.');
        }

        DB::transaction(function () use ($user, $transfer) {
            $transfer->load('items');

            foreach ($transfer->items as $item) {
                $from = $this->stock->lockForPartAndBranch($item->part_id, $transfer->from_branch_id);
                if (! $from || bccomp((string) $from->quantity, (string) $item->quantity, 4) < 0) {
                    throw new \InvalidArgumentException('Insufficient stock at source branch for part '.$item->part_id);
                }

                $transferUnitCost = $item->unit_cost !== null
                    ? (string) $item->unit_cost
                    : $this->wac->snapshotCost($from);
                $this->stock->adjustQuantity($from, -1 * $item->quantity);

                $to = $this->stock->firstOrCreate($item->part_id, $transfer->to_branch_id);
                $to = Stock::query()->whereKey($to->id)->lockForUpdate()->firstOrFail();
                $this->wac->applyInbound($to, $item->quantity, $transferUnitCost);

                $this->movements->create([
                    'part_id' => $item->part_id,
                    'branch_id' => $transfer->from_branch_id,
                    'movement_type' => StockMovementType::TransferOut,
                    'quantity' => -1 * $item->quantity,
                    'reference_id' => $transfer->id,
                    'reference_type' => 'stock_transfer',
                    'notes' => null,
                    'created_by' => $user->id,
                    'created_at' => now(),
                ]);

                $this->movements->create([
                    'part_id' => $item->part_id,
                    'branch_id' => $transfer->to_branch_id,
                    'movement_type' => StockMovementType::TransferIn,
                    'quantity' => $item->quantity,
                    'reference_id' => $transfer->id,
                    'reference_type' => 'stock_transfer',
                    'notes' => null,
                    'created_by' => $user->id,
                    'created_at' => now(),
                ]);

                $this->lowStock->notifyIfNeeded($item->part_id, $transfer->from_branch_id);
                $this->lowStock->notifyIfNeeded($item->part_id, $transfer->to_branch_id);
            }

            $transfer->status = StockTransferStatus::Completed;
            $transfer->save();
        });

        if ($recordBranchCharge) {
            $this->branchFinance->createChargeFromTransfer(
                $user,
                $transfer->fresh(['items.part', 'fromBranch', 'toBranch']),
                $valuation
            );
        }

        $this->audit->record($user, 'transfer.complete', 'stock_transfer', $transfer->id, null, $transfer->fresh()->toArray());
        $this->dashboardCache->forgetAllSummaries();

        return $transfer->fresh(['items']);
    }

    public function cancel(User $user, StockTransfer $transfer): void
    {
        if ($transfer->status !== StockTransferStatus::Pending) {
            throw new \InvalidArgumentException('Only pending transfers can be cancelled.');
        }

        $before = $transfer->toArray();
        $transfer->status = StockTransferStatus::Cancelled;
        $transfer->save();

        $this->audit->record($user, 'transfer.cancel', 'stock_transfer', $transfer->id, $before, $transfer->toArray());
    }
}
