<?php

namespace App\Http\Resources;

use App\Transformers\CapitalSettingsTransformer;
use App\Transformers\OwnerCashOutTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OwnerCashOutResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{cash_out: \App\Models\OwnerCashOut, settings: array<string, mixed>} $payload */
        $payload = $this->resource;

        return [
            'cash_out' => OwnerCashOutTransformer::transform($payload['cash_out']),
            'capital' => CapitalSettingsTransformer::transform($payload['settings']),
        ];
    }
}
