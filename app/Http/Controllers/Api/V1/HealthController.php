<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Agent\AgentCoverageService;
use App\Support\Agent\AgentRequestContext;
use App\Support\CentinelaSession;
use App\Support\YearContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    public function __invoke(
        Request $request,
        AgentRequestContext $context,
        YearContext $yearContext,
        AgentCoverageService $coverage,
    ): JsonResponse {
        $year = $context->year();
        $defaultYear = $yearContext->resolveDefaultYear();

        return response()->json([
            'status' => 'ok',
            'surface' => 'api/v1',
            'version' => CentinelaSession::resolveDeployedVersionLabel(),
            'agent_api_enabled' => (bool) config('centinela.agent_api.enabled', false),
            'year' => $year->ano,
            'default_year' => $defaultYear->ano,
            'request_id' => $request->attributes->get('agent_request_id'),
            'cobertura' => $coverage->forYear($year),
        ]);
    }
}
