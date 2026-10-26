<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Year;
use Illuminate\Http\JsonResponse;

class YearsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $years = Year::query()
            ->orderByDesc('ano')
            ->get(['id', 'ano', 'default'])
            ->map(fn (Year $y) => [
                'id' => $y->id,
                'ano' => $y->ano,
                'default' => (bool) $y->default,
            ])
            ->values()
            ->all();

        return response()->json(['data' => $years]);
    }
}
