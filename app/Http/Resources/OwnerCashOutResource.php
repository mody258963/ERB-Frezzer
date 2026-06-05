<?php

namespace App\Http\Resources;

use App\Transformers\OwnerCashOutTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\OwnerCashOut */
class OwnerCashOutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return OwnerCashOutTransformer::transform($this->resource);
    }
}
