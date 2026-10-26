<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\YearContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stub P3a (SDD-248): health de superficie agentes. Dominio → SDD-249/250.
 */
class AgentHealthController extends Controller
{
    public function __invoke(Request $request, YearContext $yearContext): JsonResponse
    {
        $year = $yearContext->current();

        return response()->json([
            'status' => 'ok',
            'surface' => str_starts_with($request->path(), 'api/mcp') ? 'mcp' : 'api/v1',
            'year' => $year?->ano,
            'request_id' => $request->attributes->get('agent_request_id'),
        ]);
    }
}
