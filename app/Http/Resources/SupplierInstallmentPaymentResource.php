<?php

namespace App\Http\Resources;

use App\Transformers\SupplierPaymentTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SupplierInstallmentPayment */
class SupplierInstallmentPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return SupplierPaymentTransformer::transformHistoryRow($this->resource);
    }
}
