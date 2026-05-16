<?php

namespace App\Http\Resources;

use App\Transformers\ProductReturnTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProductReturn */
class ProductReturnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ProductReturnTransformer::transform($this->resource);
    }
}
