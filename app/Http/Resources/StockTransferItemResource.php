<?php

namespace App\Http\Resources;

use App\Transformers\StockTransferItemTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockTransferItem */
class StockTransferItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return StockTransferItemTransformer::transform($this->resource);
    }
}
