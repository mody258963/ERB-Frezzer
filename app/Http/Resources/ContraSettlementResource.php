<?php

namespace App\Http\Resources;

use App\Transformers\ContraSettlementTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ContraSettlement */
class ContraSettlementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ContraSettlementTransformer::transform($this->resource);
    }
}
