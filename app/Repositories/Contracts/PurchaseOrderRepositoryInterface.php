<?php

namespace App\Repositories\Contracts;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PurchaseOrderRepositoryInterface
{
    public function paginate(?User $user, array $filters, int $perPage = 25): LengthAwarePaginator;

    public function findWithRelations(string $id): ?PurchaseOrder;

    public function nextPoNumber(): string;

    public function create(array $po, array $items): PurchaseOrder;

    public function save(PurchaseOrder $po): void;
}
