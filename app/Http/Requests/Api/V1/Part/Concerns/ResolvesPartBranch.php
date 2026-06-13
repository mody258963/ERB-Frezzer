<?php

namespace App\Http\Requests\Api\V1\Part\Concerns;

use App\Support\BranchVisibility;

trait ResolvesPartBranch
{
    protected function resolvedPartBranchId(): ?string
    {
        return BranchVisibility::activeBranchId($this->user())
            ?? $this->input('branch_id');
    }
}
