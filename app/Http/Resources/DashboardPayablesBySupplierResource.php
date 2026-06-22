<?php

namespace App\Http\Resources;

use App\Transformers\DashboardPayablesBySupplierTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin list<array<string, mixed>> */
class DashboardPayablesBySupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->resource;

        return DashboardPayablesBySupplierTransformer::transform($rows);
    }
}
