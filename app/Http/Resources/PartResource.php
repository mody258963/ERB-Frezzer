<?php

namespace App\Http\Resources;

use App\Transformers\PartTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Part */
class PartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return PartTransformer::transform($this->resource);
    }
}
