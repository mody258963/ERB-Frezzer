<?php

namespace App\Http\Resources;

use App\Transformers\DashboardPayablesTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps dashboard payables payload with installment collections.
 *
 * @mixin array<string, mixed>
 */
class DashboardPayablesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->resource;

        return DashboardPayablesTransformer::transform($payload);
    }
}
