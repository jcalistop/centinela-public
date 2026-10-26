<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\NonAdminDashboardService;
use App\Support\Agent\AgentErrorEnvelope;
use App\Support\Agent\AgentRequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardAlertasController extends Controller
{
    public function __invoke(
        Request $request,
        AgentRequestContext $context,
        NonAdminDashboardService $dashboard,
    ): JsonResponse {
        try {
            $user = $context->user($request);
            $ssId = $context->ssId($request);
            $year = $context->year();
        } catch (\RuntimeException $e) {
            return AgentErrorEnvelope::json('FORBIDDEN', $e->getMessage(), 403);
        }

        $alerts = $dashboard->alertsFor($user, $year, $ssId);
        $evaluation = $dashboard->alertsEvaluationFor($user, $year, $ssId, $alerts);

        return response()->json([
            'data' => $alerts,
            'meta' => [
                'year' => $year->ano,
                'cen_hie_dependency_id' => $ssId,
                'count' => count($alerts),
                'last_evaluated_at' => $evaluation['last_evaluated_at'],
                'reglas_evaluadas' => $evaluation['reglas_evaluadas'],
            ],
        ]);
    }
}
