<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\HealthResource;
use Illuminate\Http\Request;

class HealthController
{
    public function __invoke(Request $request): HealthResource
    {
        return new HealthResource([
            'status' => 'ok',
            'service' => config('app.name'),
        ]);
    }
}
