<?php

namespace App\Http\Resources;

use App\Transformers\InventoryValuationRowTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin array<string, mixed> */
class InventoryValuationRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $row */
        $row = $this->resource;

        return InventoryValuationRowTransformer::transform($row);
    }
}
