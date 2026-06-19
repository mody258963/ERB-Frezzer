<?php

namespace App\Services;

use App\Enums\CapitalAdjustmentType;
use App\Enums\SettlementPaymentMethod;
use App\Models\Branch;
use App\Models\CapitalAdjustment;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Invoice;
use App\Models\OwnerCashOut;
use App\Models\ProductReturn;
use App\Models\SaturdaySettlement;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\SupplierInstallment;
use App\Models\SupplierInstallmentPayment;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
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
        $openingCash = $this->capitalAmount($branchId);
        $cashSnapshot = $this->realizedCashSnapshot($openingCash, now()->startOfWeek(), now()->endOfWeek(), $branchId);
        $snapshot = $this->financingSnapshot($openingCash, $branchId, $cashSnapshot['cash_on_hand_realized']);
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
            'opening_cash_balance' => $openingCash,
            'capital_amount' => $openingCash,
            'business_capital' => $snapshot['business_capital'],
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
     * رأس المال = مخزون (تكلفة) + نقد فعلي في الدرج.
     */
    public function businessCapitalTotal(?string $branchId = null): float
    {
        $openingCash = $this->capitalAmount($branchId);
        $inventory = $this->financingSnapshot($openingCash, $branchId)['inventory_at_cost'];
        $cashOnHand = $this->realizedCashSnapshot(
            $openingCash,
            now()->startOfWeek(),
            now()->endOfWeek(),
            $branchId,
        )['cash_on_hand_realized'];

        return (float) bcadd((string) $inventory, (string) $cashOnHand, 2);
    }

    /**
     * Real-cash ledger. Opening cash (admin-set) + lifetime cash in − lifetime cash out.
     *
     * @return array<string, float>
     */
    public function realizedCashSnapshot(
        float $openingCashBalance,
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $branchId = null,
    ): array {
        $mustCollect = $this->scopedReceivablesTotal($branchId);
        $mustPay = $this->scopedSupplierDebtTotal($branchId);

        $cashSalesTotal = Invoice::query()
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->where('payment_type', 'cash')
            ->sum('total');
        $cashSalesWeekly = Invoice::query()
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->where('payment_type', 'cash')
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->sum('total');

        $customerPaymentsTotal = CustomerPayment::query()
            ->when($branchId, fn ($q, $id) => $q->whereHas('customer', fn ($c) => $c->where('branch_id', $id)))
            ->where('payment_method', '!=', SettlementPaymentMethod::Offset->value)
            ->sum('amount');
        $customerPaymentsWeekly = CustomerPayment::query()
            ->when($branchId, fn ($q, $id) => $q->whereHas('customer', fn ($c) => $c->where('branch_id', $id)))
            ->where('payment_method', '!=', SettlementPaymentMethod::Offset->value)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->sum('amount');

        $settlementInTotal = SaturdaySettlement::query()
            ->when($branchId, fn ($q, $id) => $q->whereHas('invoices', fn ($i) => $i->where('branch_id', $id)))
            ->where('payment_method', '!=', SettlementPaymentMethod::Offset->value)
            ->sum('total_amount');
        $settlementInWeekly = SaturdaySettlement::query()
            ->when($branchId, fn ($q, $id) => $q->whereHas('invoices', fn ($i) => $i->where('branch_id', $id)))
            ->where('payment_method', '!=', SettlementPaymentMethod::Offset->value)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->sum('total_amount');

        $supplierPaymentsTotal = SupplierInstallmentPayment::query()
            ->when($branchId, fn ($q, $id) => $q->whereHas('installment.purchaseOrder', fn ($po) => $po->where('branch_id', $id)))
            ->where('payment_method', '!=', SettlementPaymentMethod::Offset->value)
            ->sum('amount');
        $supplierPaymentsWeekly = SupplierInstallmentPayment::query()
            ->when($branchId, fn ($q, $id) => $q->whereHas('installment.purchaseOrder', fn ($po) => $po->where('branch_id', $id)))
            ->where('payment_method', '!=', SettlementPaymentMethod::Offset->value)
            ->where('paid_at', '>=', $from)
            ->where('paid_at', '<=', $to)
            ->sum('amount');

        $customerRefundsCashOutTotal = ProductReturn::query()
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->where('return_type', 'customer_return')
            ->where('status', 'completed')
            ->whereIn('resolution', ['refund_cash', 'writeoff'])
            ->sum('total_value');
        $customerRefundsCashOutWeekly = ProductReturn::query()
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->where('return_type', 'customer_return')
            ->where('status', 'completed')
            ->whereIn('resolution', ['refund_cash', 'writeoff'])
            ->where(function ($q) use ($from, $to) {
                $q->where(function ($inner) use ($from, $to) {
                    $inner->whereNotNull('completed_at')
                        ->where('completed_at', '>=', $from)
                        ->where('completed_at', '<=', $to);
                })->orWhere(function ($inner) use ($from, $to) {
                    $inner->whereNull('completed_at')
                        ->where('updated_at', '>=', $from)
                        ->where('updated_at', '<=', $to);
                });
            })
            ->sum('total_value');

        $ownerCashOutTotal = OwnerCashOut::query()
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->sum('amount');
        $ownerCashOutWeekly = OwnerCashOut::query()
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->sum('amount');

        $cashInLifetime = (float) bcadd(
            bcadd((string) $cashSalesTotal, (string) $customerPaymentsTotal, 2),
            (string) $settlementInTotal,
            2
        );
        $cashOutLifetime = (float) bcadd(
            bcadd((string) $supplierPaymentsTotal, (string) $customerRefundsCashOutTotal, 2),
            (string) $ownerCashOutTotal,
            2
        );
        $cashOnHand = (float) bcsub(
            bcadd((string) $openingCashBalance, (string) $cashInLifetime, 2),
            (string) $cashOutLifetime,
            2
        );

        $cashInWeekly = (float) bcadd(
            bcadd((string) $cashSalesWeekly, (string) $customerPaymentsWeekly, 2),
            (string) $settlementInWeekly,
            2
        );
        $cashOutWeekly = (float) bcadd(
            bcadd((string) $supplierPaymentsWeekly, (string) $customerRefundsCashOutWeekly, 2),
            (string) $ownerCashOutWeekly,
            2
        );

        return [
            'must_collect_customers' => (float) $mustCollect,
            'must_pay_suppliers' => (float) $mustPay,
            'cash_on_hand_realized' => $cashOnHand,
            'lifetime_cash_in_realized' => $cashInLifetime,
            'lifetime_cash_out_realized' => $cashOutLifetime,
            'period_cash_in_realized' => $cashInWeekly,
            'period_cash_out_realized' => $cashOutWeekly,
            'period_net_cash_flow_realized' => (float) bcsub((string) $cashInWeekly, (string) $cashOutWeekly, 2),
        ];
    }

    /**
     * How capital is deployed. business_capital = inventory_at_cost + cash_on_hand_realized.
     *
     * @return array<string, float>
     */
    public function financingSnapshot(
        float $openingCashBalance,
        ?string $branchId = null,
        ?float $cashOnHandRealized = null,
    ): array {
        $stockQuery = Stock::query()->forActiveParts();
        if ($branchId !== null) {
            $stockQuery->where('branch_id', $branchId);
        }
        $stockAtCost = (float) ($stockQuery
            ->selectRaw('SUM(quantity * average_cost) as v')
            ->value('v') ?? 0);

        $receivables = $this->scopedReceivablesTotal($branchId);
        $supplierDebt = $this->scopedSupplierDebtTotal($branchId);

        if ($cashOnHandRealized === null) {
            $cashOnHandRealized = $this->realizedCashSnapshot(
                $openingCashBalance,
                now()->startOfWeek(),
                now()->endOfWeek(),
                $branchId,
            )['cash_on_hand_realized'];
        }

        $businessCapital = (float) bcadd((string) $stockAtCost, (string) $cashOnHandRealized, 2);

        $deployed = (float) bcadd((string) $stockAtCost, (string) $receivables, 2);
        $legacyEstimatedAvailable = (float) bcsub(
            bcsub((string) $openingCashBalance, (string) $deployed, 2),
            (string) $supplierDebt,
            2
        );

        return [
            'inventory_at_cost' => $stockAtCost,
            'cash_on_hand_realized' => $cashOnHandRealized,
            'opening_cash_balance' => $openingCashBalance,
            'business_capital' => $businessCapital,
            'customer_receivables' => $receivables,
            'supplier_debt' => $supplierDebt,
            'deployed_capital' => $deployed,
            'estimated_available' => $legacyEstimatedAvailable,
            'legacy_estimated_available' => $legacyEstimatedAvailable,
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
