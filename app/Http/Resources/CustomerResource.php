<?php

namespace App\Http\Resources;

use App\Transformers\CustomerTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Customer */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return CustomerTransformer::transform($this->resource);
    }
}
