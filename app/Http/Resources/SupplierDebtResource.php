<?php

namespace App\Http\Resources;

use App\Transformers\SupplierDebtTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin array<string, mixed> */
class SupplierDebtResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->resource;

        return SupplierDebtTransformer::transform($payload);
    }
}
