<?php

namespace App\Http\Resources;

use App\Transformers\StockTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Stock */
class StockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return StockTransformer::transform($this->resource);
    }
}
