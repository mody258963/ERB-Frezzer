<?php

namespace App\Services;

use App\Models\CapitalAdjustment;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CapitalService
{
    public function settings(): CompanySetting
    {
        $setting = CompanySetting::query()->first();

        if ($setting === null) {
            $setting = CompanySetting::query()->create([
                'capital_amount' => 0,
                'currency' => 'EGP',
            ]);
        }

        return $setting->loadMissing('updater');
    }

    /**
     * @return array<string, mixed>
     */
    public function showWithSnapshot(): array
    {
        $setting = $this->settings();
        $snapshot = $this->financingSnapshot((float) $setting->capital_amount);

        return [
            'capital_amount' => (float) $setting->capital_amount,
            'currency' => $setting->currency,
            'notes' => $setting->notes,
            'updated_at' => $setting->updated_at?->toIso8601String(),
            'updated_by' => $setting->updater ? [
                'id' => $setting->updater->id,
                'name' => $setting->updater->name,
            ] : null,
            'financing_snapshot' => $snapshot,
        ];
    }

    public function update(User $user, float $newAmount, ?string $reason = null, ?string $notes = null): CompanySetting
    {
        $setting = $this->settings();
        $previous = (float) $setting->capital_amount;
        $newAmount = max(0, $newAmount);
        $change = (float) bcsub((string) $newAmount, (string) $previous, 2);

        CapitalAdjustment::query()->create([
            'previous_amount' => $previous,
            'new_amount' => $newAmount,
            'change_amount' => $change,
            'reason' => $reason,
            'created_by' => $user->id,
        ]);

        $setting->capital_amount = $newAmount;
        if ($notes !== null) {
            $setting->notes = $notes;
        }
        $setting->updated_by = $user->id;
        $setting->save();

        return $setting->fresh(['updater']);
    }

    public function adjustments(int $perPage = 25): LengthAwarePaginator
    {
        return CapitalAdjustment::query()
            ->with('creator')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Rough view of how capital is deployed (no full cash ledger).
     *
     * @return array<string, float>
     */
    public function financingSnapshot(float $capitalAmount): array
    {
        $stockAtCost = (float) (Stock::query()
            ->join('parts', 'parts.id', '=', 'stock.part_id')
            ->selectRaw('SUM(stock.quantity * parts.cost_price) as v')
            ->value('v') ?? 0);

        $receivables = (float) Customer::query()->sum('outstanding_balance');
        $supplierDebt = (float) Supplier::query()->sum('total_debt');

        $deployed = (float) bcadd((string) $stockAtCost, (string) $receivables, 2);
        $estimatedAvailable = (float) bcsub(
            bcsub((string) $capitalAmount, (string) $deployed, 2),
            (string) $supplierDebt,
            2
        );

        return [
            'inventory_at_cost' => $stockAtCost,
            'customer_receivables' => $receivables,
            'supplier_debt' => $supplierDebt,
            'deployed_capital' => $deployed,
            'estimated_available' => $estimatedAvailable,
        ];
    }
}
