<?php

namespace App\Http\Resources;

use App\Transformers\SettlementUpcomingRowTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Credit customer totals due before Saturday settlement (see SaturdaySettlementRepository::upcomingTotals).
 */
class SettlementUpcomingRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return SettlementUpcomingRowTransformer::transform((object) $this->resource);
    }
}
