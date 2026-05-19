<?php

namespace App\Repositories\Eloquent;

use App\Enums\BranchFinancialEntryStatus;
use App\Enums\BranchFinancialEntryType;
use App\Models\BranchFinancialEntry;
use App\Models\User;
use App\Repositories\Contracts\BranchFinancialEntryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class BranchFinancialEntryRepository implements BranchFinancialEntryRepositoryInterface
{
    public function paginate(?User $user, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = BranchFinancialEntry::query()
            ->with(['creditorBranch', 'debtorBranch', 'creator']);

        if ($user?->branch_id) {
            $branchId = $user->branch_id;
            $query->where(function ($q) use ($branchId) {
                $q->where('creditor_branch_id', $branchId)
                    ->orWhere('debtor_branch_id', $branchId);
            });
        }

        $query->when($filters['creditor_branch_id'] ?? null, fn ($q, $id) => $q->where('creditor_branch_id', $id))
            ->when($filters['debtor_branch_id'] ?? null, fn ($q, $id) => $q->where('debtor_branch_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['entry_type'] ?? null, fn ($q, $t) => $q->where('entry_type', $t));

        return $query->latest()->paginate($perPage);
    }

    public function find(string $id): ?BranchFinancialEntry
    {
        return BranchFinancialEntry::query()
            ->with(['creditorBranch', 'debtorBranch', 'creator', 'settler'])
            ->find($id);
    }

    public function create(array $data): BranchFinancialEntry
    {
        return BranchFinancialEntry::query()->create($data);
    }

    public function save(BranchFinancialEntry $entry): void
    {
        $entry->save();
    }

    public function nextEntryNumber(): string
    {
        $prefix = 'BFE-'.now()->format('Ymd');
        $last = BranchFinancialEntry::query()
            ->where('entry_number', 'like', $prefix.'%')
            ->orderByDesc('entry_number')
            ->value('entry_number');

        $seq = $last ? ((int) substr((string) $last, -4)) + 1 : 1;

        return $prefix.'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function openChargesBetween(string $creditorBranchId, string $debtorBranchId): Collection
    {
        return BranchFinancialEntry::query()
            ->where('creditor_branch_id', $creditorBranchId)
            ->where('debtor_branch_id', $debtorBranchId)
            ->where('entry_type', BranchFinancialEntryType::Charge->value)
            ->where('status', BranchFinancialEntryStatus::Open->value)
            ->orderBy('created_at')
            ->get();
    }
}
