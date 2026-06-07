<?php

namespace App\Http\Resources;

use App\Transformers\CustomerPaymentTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CustomerPayment */
class CustomerPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return CustomerPaymentTransformer::transform($this->resource);
    }
}
