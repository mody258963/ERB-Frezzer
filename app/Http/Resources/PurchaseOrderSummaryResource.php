<?php

namespace App\Http\Resources;

use App\Transformers\PurchaseOrderSummaryTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseOrder */
class PurchaseOrderSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return PurchaseOrderSummaryTransformer::transform($this->resource);
    }
}
