<?php

namespace App\Http\Resources;

use App\Transformers\BranchFinancialEntryTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\BranchFinancialEntry */
class BranchFinancialEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return BranchFinancialEntryTransformer::transform($this->resource);
    }
}
