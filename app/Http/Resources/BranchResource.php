<?php

namespace App\Http\Resources;

use App\Transformers\BranchTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Branch */
class BranchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return BranchTransformer::transform($this->resource);
    }
}
