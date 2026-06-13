<?php

namespace App\Http\Requests\Api\V1\Supplier\Concerns;

use App\Support\BranchVisibility;

trait ResolvesSupplierBranch
{
    protected function resolvedSupplierBranchId(): ?string
    {
        return BranchVisibility::activeBranchId($this->user())
            ?? $this->input('branch_id');
    }
}
