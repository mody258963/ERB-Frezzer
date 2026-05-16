<?php

namespace App\Http\Resources;

use App\Transformers\SupplierDebtAgingRowTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Raw row from reports/suppliers aging query */
class SupplierDebtAgingRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return SupplierDebtAgingRowTransformer::transform((object) $this->resource);
    }
}
