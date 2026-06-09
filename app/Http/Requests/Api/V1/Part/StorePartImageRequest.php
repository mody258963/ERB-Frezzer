<?php

namespace App\Http\Requests\Api\V1\Part;

use App\Http\Requests\Api\V1\ApiFormRequest;

class StorePartImageRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }
}
