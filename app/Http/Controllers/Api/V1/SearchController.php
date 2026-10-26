<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Agent\AgentSearchService;
use App\Support\Agent\AgentErrorEnvelope;
use App\Support\Agent\AgentRequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(
        Request $request,
        AgentRequestContext $context,
        AgentSearchService $search,
    ): JsonResponse {
        $result = $search->search(
            $context->year(),
            $request->query('q'),
            $request->query('per_page'),
        );

        if (! $result['ok']) {
            return AgentErrorEnvelope::json(
                $result['code'],
                $result['message'],
                $result['status'],
                $result['details'] ?? []
            );
        }

        return response()->json($result['payload']);
    }
}
