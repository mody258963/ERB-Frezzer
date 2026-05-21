<?php

namespace App\Http\Resources;

use App\Transformers\PartCategoryTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PartCategory */
class PartCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return PartCategoryTransformer::transform($this->resource);
    }
}
