<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Health\HealthStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    /**
     * Responde con el estado de salud básico de la aplicación.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $compact = $request->boolean('compact', false);

        return response()->json(app(HealthStatusService::class)->status($compact));
    }
}
