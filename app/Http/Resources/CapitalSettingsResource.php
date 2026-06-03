<?php

namespace App\Http\Resources;

use App\Transformers\CapitalSettingsTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin array<string, mixed> */
class CapitalSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $row */
        $row = $this->resource;

        return CapitalSettingsTransformer::transform($row);
    }
}
