<?php

namespace App\Http\Resources;

use App\Transformers\ReturnItemTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ReturnItem */
class ReturnItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ReturnItemTransformer::transform($this->resource);
    }
}
