<?php

namespace App\Repositories\Eloquent;

use App\Enums\BranchFinancialEntryStatus;
use App\Enums\BranchFinancialEntryType;
use App\Models\BranchFinancialEntry;
use App\Models\User;
use App\Repositories\Contracts\BranchFinancialEntryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class BranchFinancialEntryRepository extends BaseRepository implements BranchFinancialEntryRepositoryInterface
{
    protected function modelClass(): string
    {
        return BranchFinancialEntry::class;
    }

    protected function defaultRelations(): array
    {
        return ['creditorBranch', 'debtorBranch', 'creator', 'settler'];
    }

    public function paginate(?User $user, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->newQuery()->with(['creditorBranch', 'debtorBranch', 'creator']);

        if ($user?->branch_id) {
            $branchId = $user->branch_id;
            $query->where(function ($q) use ($branchId) {
                $q->where('creditor_branch_id', $branchId)
                    ->orWhere('debtor_branch_id', $branchId);
            });
        }

        return $query
            ->when($filters['creditor_branch_id'] ?? null, fn ($q, $id) => $q->where('creditor_branch_id', $id))
            ->when($filters['debtor_branch_id'] ?? null, fn ($q, $id) => $q->where('debtor_branch_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['entry_type'] ?? null, fn ($q, $t) => $q->where('entry_type', $t))
            ->latest()
            ->paginate($perPage);
    }

    public function find(string $id): ?BranchFinancialEntry
    {
        return $this->findById($id);
    }

    public function create(array $data): BranchFinancialEntry
    {
        /** @var BranchFinancialEntry */
        return $this->createRecord($data);
    }

    public function save(BranchFinancialEntry $entry): void
    {
        $this->saveRecord($entry);
    }

    public function nextEntryNumber(): string
    {
        $prefix = 'BFE-'.now()->format('Ymd');
        $last = $this->newQuery()
            ->where('entry_number', 'like', $prefix.'%')
            ->orderByDesc('entry_number')
            ->value('entry_number');

        $seq = $last ? ((int) substr((string) $last, -4)) + 1 : 1;

        return $prefix.'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function openChargesBetween(string $creditorBranchId, string $debtorBranchId): Collection
    {
        return $this->newQuery()
            ->where('creditor_branch_id', $creditorBranchId)
            ->where('debtor_branch_id', $debtorBranchId)
            ->where('entry_type', BranchFinancialEntryType::Charge->value)
            ->where('status', BranchFinancialEntryStatus::Open->value)
            ->whereNull('voided_at')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return Collection<int, BranchFinancialEntry>
     */
    public function activePaymentsBetween(string $creditorBranchId, string $debtorBranchId): Collection
    {
        return $this->newQuery()
            ->where('creditor_branch_id', $creditorBranchId)
            ->where('debtor_branch_id', $debtorBranchId)
            ->where('entry_type', BranchFinancialEntryType::Payment->value)
            ->whereNull('voided_at')
            ->orderBy('created_at')
            ->get();
    }
}
