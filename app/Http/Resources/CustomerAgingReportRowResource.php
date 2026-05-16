<?php

namespace App\Http\Resources;

use App\Transformers\CustomerAgingReportRowTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Raw row from reports/customers aging query */
class CustomerAgingReportRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return CustomerAgingReportRowTransformer::transform((object) $this->resource);
    }
}
