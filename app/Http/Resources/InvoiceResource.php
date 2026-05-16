<?php

namespace App\Http\Resources;

use App\Transformers\InvoiceTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return InvoiceTransformer::transform($this->resource);
    }
}
