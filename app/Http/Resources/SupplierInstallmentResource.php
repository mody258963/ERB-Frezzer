<?php

namespace App\Http\Resources;

use App\Transformers\SupplierInstallmentTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupplierInstallment */
class SupplierInstallmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return SupplierInstallmentTransformer::transform($this->resource);
    }
}
