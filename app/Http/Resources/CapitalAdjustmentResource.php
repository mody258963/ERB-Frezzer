<?php

namespace App\Http\Resources;

use App\Transformers\CapitalAdjustmentTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CapitalAdjustment */
class CapitalAdjustmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return CapitalAdjustmentTransformer::transform($this->resource);
    }
}
