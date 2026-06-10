<?php

namespace App\Services;

use App\Enums\CapitalAdjustmentType;
use App\Models\Branch;
use App\Models\CapitalAdjustment;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\OwnerCashOut;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\SupplierInstallment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CapitalService
{
    private const PROFIT_EPOCH = '2020-01-01';

    public function __construct(
        private FinancialMetricsService $financialMetrics,
    ) {}

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

    public function capitalAmount(?string $branchId = null): float
    {
        if ($branchId !== null) {
            return (float) Branch::query()->whereKey($branchId)->value('capital_amount');
        }

        return (float) Branch::query()->sum('capital_amount');
    }

    /**
     * @return array<string, mixed>
     */
    public function showWithSnapshot(?string $branchId = null): array
    {
        $setting = $this->settings();
        $capitalAmount = $this->capitalAmount($branchId);
        $snapshot = $this->financingSnapshot($capitalAmount, $branchId);
        $profitSnapshot = $this->profitWithdrawalSnapshot($branchId);

        $branch = $branchId !== null
            ? Branch::query()->find($branchId)
            : null;

        return [
            'branch_id' => $branchId,
            'branch' => $branch ? [
                'id' => $branch->id,
                'name' => $branch->name,
            ] : null,
            'capital_amount' => $capitalAmount,
            'currency' => $setting->currency,
            'notes' => $setting->notes,
            'updated_at' => $setting->updated_at?->toIso8601String(),
            'updated_by' => $setting->updater ? [
                'id' => $setting->updater->id,
                'name' => $setting->updater->name,
            ] : null,
            'financing_snapshot' => $snapshot,
            'profit_withdrawal' => $profitSnapshot,
        ];
    }

    public function update(
        User $user,
        float $newAmount,
        ?string $reason = null,
        ?string $notes = null,
        ?string $branchId = null,
    ): Branch {
        $branchId = $this->requireBranchIdForUpdate($branchId);
        $branch = Branch::query()->findOrFail($branchId);
        $previous = (float) $branch->capital_amount;
        $newAmount = max(0, $newAmount);
        $change = (float) bcsub((string) $newAmount, (string) $previous, 2);

        CapitalAdjustment::query()->create([
            'type' => CapitalAdjustmentType::ManualSet,
            'branch_id' => $branchId,
            'previous_amount' => $previous,
            'new_amount' => $newAmount,
            'change_amount' => $change,
            'reason' => $reason,
            'created_by' => $user->id,
        ]);

        $branch->capital_amount = $newAmount;
        $branch->save();

        if ($notes !== null) {
            $setting = $this->settings();
            $setting->notes = $notes;
            $setting->updated_by = $user->id;
            $setting->save();
        }

        return $branch->fresh();
    }

    public function adjustments(int $perPage = 25, ?string $branchId = null): LengthAwarePaginator
    {
        return CapitalAdjustment::query()
            ->with(['creator', 'branch'])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Owner withdraws cash from withdrawable profit — does NOT reduce business capital.
     *
     * @return array{cash_out: OwnerCashOut, settings: array<string, mixed>}
     */
    public function cashOut(
        User $user,
        float $amount,
        ?string $reason = null,
        ?string $notes = null,
        ?string $branchId = null,
    ): array {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Cash out amount must be greater than zero.');
        }

        $snapshot = $this->profitWithdrawalSnapshot($branchId);
        $withdrawable = (string) $snapshot['withdrawable_profit'];

        if (bccomp((string) $amount, $withdrawable, 2) > 0) {
            throw new \InvalidArgumentException(
                'Cash out amount exceeds withdrawable profit ('.number_format((float) $withdrawable, 2).'). '
                .'Owner draws are deducted from profit margin, not business capital.',
            );
        }

        $cashOut = OwnerCashOut::query()->create([
            'amount' => $amount,
            'branch_id' => $branchId,
            'reason' => $reason,
            'notes' => $notes,
            'created_by' => $user->id,
            'created_at' => now(),
        ]);

        return [
            'cash_out' => $cashOut->load(['creator', 'branch']),
            'settings' => $this->showWithSnapshot($branchId),
        ];
    }

    public function cashOuts(int $perPage = 25, ?string $branchId = null): LengthAwarePaginator
    {
        return OwnerCashOut::query()
            ->with(['creator', 'branch'])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Realized profit minus prior owner cash outs.
     *
     * @return array{
     *     realized_profit: float,
     *     total_withdrawn: float,
     *     withdrawable_profit: float,
     *     branch_id: ?string
     * }
     */
    public function profitWithdrawalSnapshot(?string $branchId = null): array
    {
        $from = Carbon::parse(self::PROFIT_EPOCH)->startOfDay();
        $to = now()->endOfDay();
        $totals = $this->financialMetrics->totals($from, $to, $branchId);
        $realizedProfit = (float) $totals['profit'];

        $withdrawnQuery = OwnerCashOut::query();
        if ($branchId !== null) {
            $withdrawnQuery->where('branch_id', $branchId);
        }
        $alreadyWithdrawn = (float) $withdrawnQuery->sum('amount');

        $withdrawable = (float) bcsub((string) $realizedProfit, (string) $alreadyWithdrawn, 2);
        if (bccomp((string) $withdrawable, '0', 2) < 0) {
            $withdrawable = 0.0;
        }

        return [
            'realized_profit' => $realizedProfit,
            'total_withdrawn' => $alreadyWithdrawn,
            'withdrawable_profit' => $withdrawable,
            'branch_id' => $branchId,
        ];
    }

    /**
     * Rough view of how capital is deployed (no full cash ledger).
     *
     * @return array<string, float>
     */
    public function financingSnapshot(float $capitalAmount, ?string $branchId = null): array
    {
        $stockQuery = Stock::query()
            ->join('parts', 'parts.id', '=', 'stock.part_id');
        if ($branchId !== null) {
            $stockQuery->where('stock.branch_id', $branchId);
        }
        $stockAtCost = (float) ($stockQuery
            ->selectRaw('SUM(stock.quantity * parts.cost_price) as v')
            ->value('v') ?? 0);

        $receivables = $this->scopedReceivablesTotal($branchId);
        $supplierDebt = $this->scopedSupplierDebtTotal($branchId);

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

    private function requireBranchIdForUpdate(?string $branchId): string
    {
        if ($branchId !== null) {
            return $branchId;
        }

        if (Branch::query()->count() === 1) {
            return (string) Branch::query()->value('id');
        }

        throw new \InvalidArgumentException('branch_id is required to set business capital.');
    }

    private function scopedReceivablesTotal(?string $branchId): float
    {
        if ($branchId === null) {
            return (float) Customer::query()->sum('outstanding_balance');
        }

        return (float) Invoice::query()
            ->where('branch_id', $branchId)
            ->where('payment_type', 'credit')
            ->where('is_paid', false)
            ->get()
            ->sum(fn (Invoice $invoice) => (float) $invoice->balanceDue());
    }

    private function scopedSupplierDebtTotal(?string $branchId): float
    {
        if ($branchId === null) {
            return (float) Supplier::query()->sum('total_debt');
        }

        return (float) SupplierInstallment::query()
            ->where('is_paid', false)
            ->whereHas('purchaseOrder', fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('SUM(amount - amount_paid) as balance')
            ->value('balance') ?? 0;
    }
}
