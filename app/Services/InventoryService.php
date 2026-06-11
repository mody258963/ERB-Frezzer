<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Stock;
use App\Models\User;
use App\Repositories\Contracts\StockMovementRepositoryInterface;
use App\Repositories\Contracts\StockRepositoryInterface;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(
        private StockRepositoryInterface $stock,
        private StockMovementRepositoryInterface $movements,
        private WeightedAverageCostService $wac,
        private AuditLogService $audit,
        private DashboardCacheService $dashboardCache,
        private LowStockBroadcaster $lowStock,
    ) {}

    /**
     * @param  array{part_id: string, branch_id: string, quantity_delta: int, unit_cost?: string|float|null, reason?: ?string}  $data
     */
    public function adjust(User $user, array $data): void
    {
        DB::transaction(function () use ($user, $data) {
            $stock = $this->stock->firstOrCreate($data['part_id'], $data['branch_id']);
            $stock = Stock::query()->whereKey($stock->id)->lockForUpdate()->firstOrFail();

            if ($data['quantity_delta'] > 0) {
                $unitCost = array_key_exists('unit_cost', $data) && $data['unit_cost'] !== null
                    ? (string) $data['unit_cost']
                    : $this->wac->snapshotCost($stock);
                $this->wac->applyInbound($stock, (int) $data['quantity_delta'], $unitCost);
            } else {
                $this->stock->adjustQuantity($stock, $data['quantity_delta']);
            }

            $this->movements->create([
                'part_id' => $data['part_id'],
                'branch_id' => $data['branch_id'],
                'movement_type' => StockMovementType::Adjustment,
                'quantity' => $data['quantity_delta'],
                'reference_id' => null,
                'reference_type' => 'manual_adjustment',
                'notes' => $data['reason'] ?? null,
                'created_by' => $user->id,
                'created_at' => now(),
            ]);
        });

        $before = ['stock' => 'adjusted'];
        $this->audit->record($user, 'inventory.adjust', 'stock', $data['part_id'].'|'.$data['branch_id'], $before, $data);
        $this->lowStock->notifyIfNeeded($data['part_id'], $data['branch_id']);
        $this->dashboardCache->forgetAllSummaries();
    }
}
