<?php

namespace App\Http\Resources;

use App\Transformers\SupplierInstallmentSummaryTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupplierInstallment */
class SupplierInstallmentSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return SupplierInstallmentSummaryTransformer::transform($this->resource);
    }
}
