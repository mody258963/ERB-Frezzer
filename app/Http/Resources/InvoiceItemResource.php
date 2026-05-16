<?php

namespace App\Http\Resources;

use App\Transformers\InvoiceItemTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InvoiceItem */
class InvoiceItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return InvoiceItemTransformer::transform($this->resource);
    }
}
