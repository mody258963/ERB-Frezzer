<?php

namespace App\Http\Resources;

use App\Transformers\SaturdaySettlementTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SaturdaySettlement */
class SaturdaySettlementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return SaturdaySettlementTransformer::transform($this->resource);
    }
}
