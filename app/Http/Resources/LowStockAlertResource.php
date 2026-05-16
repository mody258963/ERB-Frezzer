<?php

namespace App\Http\Resources;

use App\Transformers\LowStockAlertTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Low-stock rows from raw stock joins (stdClass).
 *
 * @property-read object $resource
 */
class LowStockAlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return LowStockAlertTransformer::transform((object) $this->resource);
    }
}
