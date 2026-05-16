<?php

namespace App\Repositories\Contracts;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface InvoiceRepositoryInterface
{
    public function paginate(?User $user, array $filters, int $perPage = 25): LengthAwarePaginator;

    public function findWithItems(string $id): ?Invoice;

    public function pendingCredit(?User $user): Collection;

    public function nextInvoiceNumber(): string;

    public function create(array $invoice, array $items): Invoice;

    public function save(Invoice $invoice): void;
}
