<?php

namespace App\Http\Resources;

use App\Transformers\SupplierTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Supplier */
class SupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return SupplierTransformer::transform($this->resource);
    }
}
