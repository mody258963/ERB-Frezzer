<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PartUnit;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PartUnitController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'units' => PartUnit::options(),
        ]);
    }
}
