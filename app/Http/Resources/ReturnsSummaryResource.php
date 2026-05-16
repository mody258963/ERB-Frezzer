<?php

namespace App\Http\Resources;

use App\Transformers\ReturnsSummaryTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin array<string, mixed> */
class ReturnsSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->resource;

        return ReturnsSummaryTransformer::transform($payload);
    }
}
