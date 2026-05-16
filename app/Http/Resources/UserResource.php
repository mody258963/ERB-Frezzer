<?php

namespace App\Http\Resources;

use App\Transformers\UserTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return UserTransformer::transform($this->resource);
    }
}
