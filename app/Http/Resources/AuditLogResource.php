<?php

namespace App\Http\Resources;

use App\Transformers\AuditLogTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return AuditLogTransformer::transform($this->resource);
    }
}
