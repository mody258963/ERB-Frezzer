<?php

namespace App\Http\Resources;

use App\Transformers\InvoiceTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Invoice */
class InvoiceResource extends JsonResource
{
    /**
     * @var array<string, array<string, int>>|null
     */
    protected ?array $returnQuantitiesByPart = null;

    /**
     * @param  array<string, array<string, int>>  $context
     */
    public function withReturnContext(array $context): static
    {
        $this->returnQuantitiesByPart = $context;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return InvoiceTransformer::transform($this->resource, $this->returnQuantitiesByPart);
    }
}
