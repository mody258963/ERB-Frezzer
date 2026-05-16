<?php

namespace App\Http\Resources;

use App\Transformers\PurchaseOrderTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseOrder */
class PurchaseOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return PurchaseOrderTransformer::transform($this->resource);
    }
}
