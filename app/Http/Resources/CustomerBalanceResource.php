<?php

namespace App\Http\Resources;

use App\Transformers\CustomerBalanceTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin array<string, mixed> */
class CustomerBalanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->resource;

        return CustomerBalanceTransformer::transform($payload);
    }
}
