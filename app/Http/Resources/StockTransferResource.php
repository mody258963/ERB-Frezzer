<?php

namespace App\Http\Resources;

use App\Transformers\StockTransferTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockTransfer */
class StockTransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return StockTransferTransformer::transform($this->resource);
    }
}
