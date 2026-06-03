<?php

namespace App\Http\Resources;

use App\Transformers\FinancialReportTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin array<string, mixed> */
class FinancialReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->resource;

        return FinancialReportTransformer::transform($payload);
    }
}
