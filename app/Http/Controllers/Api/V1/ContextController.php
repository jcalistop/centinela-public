<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CenHieDependency;
use App\Support\Agent\AgentErrorEnvelope;
use App\Support\Agent\AgentRequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContextController extends Controller
{
    public function __invoke(Request $request, AgentRequestContext $context): JsonResponse
    {
        try {
            $user = $context->user($request);
            $ssId = $context->ssId($request);
            $year = $context->year();
        } catch (\RuntimeException $e) {
            return AgentErrorEnvelope::json('FORBIDDEN', $e->getMessage(), 403);
        }

        $ss = CenHieDependency::query()->find($ssId);

        return response()->json([
            'year' => $year->ano,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => $ssId,
            'ss_nombre' => $ss?->nombre,
            'user_id' => $user->id,
            'abilities' => ['read'],
        ]);
    }
}
