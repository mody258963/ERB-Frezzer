<?php

namespace App\Repositories\Eloquent;

use App\Models\Invoice;
use App\Models\User;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Support\BranchVisibility;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class InvoiceRepository extends BaseRepository implements InvoiceRepositoryInterface
{
    protected function modelClass(): string
    {
        return Invoice::class;
    }

    public function paginate(?User $user, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->newQuery()->with(['customer', 'branch']);

        BranchVisibility::scope($user, $query, 'branch_id');

        return $query
            ->when($filters['payment_type'] ?? null, fn ($q, $t) => $q->where('payment_type', $t))
            ->when(array_key_exists('is_paid', $filters ?? []) && $filters['is_paid'] !== null, fn ($q) => $q->where('is_paid', filter_var($filters['is_paid'], FILTER_VALIDATE_BOOLEAN)))
            ->when($filters['customer_id'] ?? null, fn ($q, $id) => $q->where('customer_id', $id))
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate($perPage);
    }

    public function findWithItems(string $id): ?Invoice
    {
        return $this->findByIdWith($id, ['items.part', 'customer', 'branch', 'creator']);
    }

    public function findOrFail(string $id): Invoice
    {
        /** @var Invoice */
        return $this->findByIdOrFail($id);
    }

    public function pendingCredit(?User $user): Collection
    {
        $query = $this->newQuery()
            ->where('payment_type', 'credit')
            ->where('is_paid', false)
            ->with(['customer', 'branch']);

        BranchVisibility::scope($user, $query, 'branch_id');

        return $query->latest()->get();
    }

    public function nextInvoiceNumber(): string
    {
        return $this->nextSequentialNumber('invoice_number', 'INV-');
    }

    public function create(array $invoice, array $items): Invoice
    {
        /** @var Invoice */
        return $this->createWithItems($invoice, $items);
    }

    public function save(Invoice $invoice): void
    {
        $this->saveRecord($invoice);
    }
}
