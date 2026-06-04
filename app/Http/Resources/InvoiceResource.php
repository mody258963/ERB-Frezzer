<?php

namespace App\Http\Resources;

use App\Transformers\InvoiceTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Invoice */
class InvoiceResource extends JsonResource
{
    /**
     * @param  array<string, array<string, int>>|null  $returnQuantitiesByPart
     */
    public function __construct(
        $resource,
        protected ?array $returnQuantitiesByPart = null,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return InvoiceTransformer::transform($this->resource, $this->returnQuantitiesByPart);
    }
}
