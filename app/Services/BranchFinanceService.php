<?php

namespace App\Services;

use App\Enums\BranchFinancialEntryStatus;
use App\Enums\BranchFinancialEntryType;
use App\Models\BranchFinancialEntry;
use App\Models\Part;
use App\Models\Stock;
use App\Models\StockTransfer;
use App\Models\User;
use App\Repositories\Contracts\BranchFinancialEntryRepositoryInterface;
use Illuminate\Support\Facades\DB;

class BranchFinanceService
{
    public function __construct(
        private BranchFinancialEntryRepositoryInterface $entries,
        private AuditLogService $audit,
    ) {}

    public function createChargeFromTransfer(User $user, StockTransfer $transfer, string $valuation = 'cost'): BranchFinancialEntry
    {
        $existing = BranchFinancialEntry::query()
            ->where('reference_type', 'stock_transfer')
            ->where('reference_id', $transfer->id)
            ->first();

        if ($existing) {
            return $existing->load(['creditorBranch', 'debtorBranch']);
        }

        $transfer->load(['items.part', 'fromBranch', 'toBranch']);

        $amount = '0';
        foreach ($transfer->items as $item) {
            $part = $item->part ?? Part::query()->find($item->part_id);

            if ($valuation === 'sell') {
                $unit = (string) ($part?->sell_price ?? 0);
            } elseif ($item->unit_cost !== null && bccomp((string) $item->unit_cost, '0', 2) > 0) {
                $unit = (string) $item->unit_cost;
            } else {
                $fromStock = Stock::query()
                    ->where('part_id', $item->part_id)
                    ->where('branch_id', $transfer->from_branch_id)
                    ->first();
                $costUnit = (string) ($fromStock?->average_cost ?? '0');
                if (bccomp($costUnit, '0', 2) <= 0) {
                    $costUnit = (string) ($part?->cost_price ?? '0');
                }
                $unit = $costUnit;
            }

            $line = bcmul($unit, (string) $item->quantity, 2);
            $amount = bcadd($amount, $line, 2);
        }

        if (bccomp($amount, '0', 2) <= 0) {
            throw new \InvalidArgumentException('Transfer has no value for inter-branch charge.');
        }

        $entry = $this->entries->create([
            'entry_number' => $this->entries->nextEntryNumber(),
            'creditor_branch_id' => $transfer->from_branch_id,
            'debtor_branch_id' => $transfer->to_branch_id,
            'amount' => $amount,
            'entry_type' => BranchFinancialEntryType::Charge,
            'status' => BranchFinancialEntryStatus::Open,
            'reference_type' => 'stock_transfer',
            'reference_id' => $transfer->id,
            'description' => sprintf(
                'Stock transfer %s → %s (%d line(s), %s basis)',
                $transfer->fromBranch?->name ?? $transfer->from_branch_id,
                $transfer->toBranch?->name ?? $transfer->to_branch_id,
                $transfer->items->count(),
                $valuation
            ),
            'notes' => $transfer->notes,
            'created_by' => $user->id,
        ]);

        $this->audit->record($user, 'branch_finance.charge', 'branch_financial_entry', $entry->id, null, $entry->toArray());

        return $entry->load(['creditorBranch', 'debtorBranch']);
    }

    /**
     * @param  array{creditor_branch_id: string, debtor_branch_id: string, amount: float|int|string, description?: string, notes?: string}  $data
     */
    public function recordManualCharge(User $user, array $data): BranchFinancialEntry
    {
        if ($data['creditor_branch_id'] === $data['debtor_branch_id']) {
            throw new \InvalidArgumentException('Creditor and debtor branch must differ.');
        }

        $entry = $this->entries->create([
            'entry_number' => $this->entries->nextEntryNumber(),
            'creditor_branch_id' => $data['creditor_branch_id'],
            'debtor_branch_id' => $data['debtor_branch_id'],
            'amount' => $data['amount'],
            'entry_type' => BranchFinancialEntryType::Charge,
            'status' => BranchFinancialEntryStatus::Open,
            'reference_type' => 'manual',
            'reference_id' => null,
            'description' => $data['description'] ?? 'Manual inter-branch charge',
            'notes' => $data['notes'] ?? null,
            'created_by' => $user->id,
        ]);

        $this->audit->record($user, 'branch_finance.manual_charge', 'branch_financial_entry', $entry->id, null, $entry->toArray());

        return $entry->load(['creditorBranch', 'debtorBranch']);
    }

    /**
     * @param  array{creditor_branch_id: string, debtor_branch_id: string, amount: float|int|string, notes?: string}  $data
     */
    public function recordPayment(User $user, array $data): BranchFinancialEntry
    {
        if ($data['creditor_branch_id'] === $data['debtor_branch_id']) {
            throw new \InvalidArgumentException('Creditor and debtor branch must differ.');
        }

        $entry = $this->entries->create([
            'entry_number' => $this->entries->nextEntryNumber(),
            'creditor_branch_id' => $data['creditor_branch_id'],
            'debtor_branch_id' => $data['debtor_branch_id'],
            'amount' => $data['amount'],
            'entry_type' => BranchFinancialEntryType::Payment,
            'status' => BranchFinancialEntryStatus::Settled,
            'reference_type' => 'manual_payment',
            'reference_id' => null,
            'description' => 'Inter-branch payment received',
            'notes' => $data['notes'] ?? null,
            'created_by' => $user->id,
            'settled_at' => now(),
            'settled_by' => $user->id,
        ]);

        $this->applyPaymentToOpenCharges($entry);

        $this->audit->record($user, 'branch_finance.payment', 'branch_financial_entry', $entry->id, null, $entry->toArray());

        return $entry->load(['creditorBranch', 'debtorBranch']);
    }

    public function settleCharge(User $user, BranchFinancialEntry $entry): BranchFinancialEntry
    {
        if ($entry->entry_type !== BranchFinancialEntryType::Charge) {
            throw new \InvalidArgumentException('Only charge entries can be settled.');
        }
        if ($entry->status === BranchFinancialEntryStatus::Settled) {
            throw new \InvalidArgumentException('Entry is already settled.');
        }

        $before = $entry->toArray();
        $entry->status = BranchFinancialEntryStatus::Settled;
        $entry->settled_at = now();
        $entry->settled_by = $user->id;
        $this->entries->save($entry);

        $this->audit->record($user, 'branch_finance.settle', 'branch_financial_entry', $entry->id, $before, $entry->toArray());

        return $entry->fresh(['creditorBranch', 'debtorBranch', 'settler']);
    }

    /**
     * Net balance: positive = debtor still owes creditor.
     *
     * @return list<array{
     *   creditor_branch_id: string,
     *   creditor_branch_name: string|null,
     *   debtor_branch_id: string,
     *   debtor_branch_name: string|null,
     *   total_charges: float,
     *   total_payments: float,
     *   balance_owed: float,
     *   open_charges_count: int
     * }>
     */
    public function balanceMatrix(?User $user): array
    {
        $query = DB::table('branch_financial_entries as e')
            ->join('branches as cb', 'cb.id', '=', 'e.creditor_branch_id')
            ->join('branches as db', 'db.id', '=', 'e.debtor_branch_id')
            ->selectRaw('e.creditor_branch_id')
            ->selectRaw('cb.name as creditor_branch_name')
            ->selectRaw('e.debtor_branch_id')
            ->selectRaw('db.name as debtor_branch_name')
            ->selectRaw("SUM(CASE WHEN e.entry_type = 'charge' THEN e.amount ELSE 0 END) as total_charges")
            ->selectRaw("SUM(CASE WHEN e.entry_type = 'payment' THEN e.amount ELSE 0 END) as total_payments")
            ->selectRaw("SUM(CASE WHEN e.entry_type = 'charge' AND e.status = 'open' THEN 1 ELSE 0 END) as open_charges_count")
            ->groupBy('e.creditor_branch_id', 'cb.name', 'e.debtor_branch_id', 'db.name');

        if ($user?->branch_id) {
            $branchId = $user->branch_id;
            $query->where(function ($q) use ($branchId) {
                $q->where('e.creditor_branch_id', $branchId)
                    ->orWhere('e.debtor_branch_id', $branchId);
            });
        }

        return collect($query->get())->map(function ($row) {
            $charges = (float) $row->total_charges;
            $payments = (float) $row->total_payments;
            $balance = (float) bcsub((string) $charges, (string) $payments, 2);

            return [
                'creditor_branch_id' => $row->creditor_branch_id,
                'creditor_branch_name' => $row->creditor_branch_name,
                'debtor_branch_id' => $row->debtor_branch_id,
                'debtor_branch_name' => $row->debtor_branch_name,
                'total_charges' => $charges,
                'total_payments' => $payments,
                'balance_owed' => $balance,
                'open_charges_count' => (int) $row->open_charges_count,
            ];
        })->filter(fn ($r) => $r['balance_owed'] > 0 || $r['open_charges_count'] > 0)
            ->values()
            ->all();
    }

    private function applyPaymentToOpenCharges(BranchFinancialEntry $payment): void
    {
        $remaining = (string) $payment->amount;
        $open = $this->entries->openChargesBetween(
            $payment->creditor_branch_id,
            $payment->debtor_branch_id
        );

        foreach ($open as $charge) {
            if (bccomp($remaining, '0', 2) <= 0) {
                break;
            }

            $chargeAmount = (string) $charge->amount;
            if (bccomp($remaining, $chargeAmount, 2) >= 0) {
                $charge->status = BranchFinancialEntryStatus::Settled;
                $charge->settled_at = $payment->settled_at ?? now();
                $charge->settled_by = $payment->settled_by ?? $payment->created_by;
                $this->entries->save($charge);
                $remaining = bcsub($remaining, $chargeAmount, 2);
            }
        }
    }
}
