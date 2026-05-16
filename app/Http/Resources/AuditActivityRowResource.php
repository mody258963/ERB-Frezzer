<?php

namespace App\Http\Resources;

use App\Transformers\AuditActivityRowTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Audit/stock rows from dashboard activity raw DB queries (stdClass).
 */
class AuditActivityRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return AuditActivityRowTransformer::transform((object) $this->resource);
    }
}
