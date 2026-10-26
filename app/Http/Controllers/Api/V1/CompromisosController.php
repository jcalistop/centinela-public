<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Agent\ComgesReadAdapter;
use App\Support\Agent\AgentPagination;
use App\Support\Agent\AgentRequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompromisosController extends Controller
{
    public function __invoke(
        Request $request,
        AgentRequestContext $context,
        ComgesReadAdapter $comges,
    ): JsonResponse {
        $q = $request->query('q');
        $q = is_string($q) ? $q : null;
        $page = $comges->paginateCommitments(
            $context->year(),
            $q,
            AgentPagination::perPage($request->query('per_page'))
        );

        return response()->json(AgentPagination::wrap(
            $page,
            $page->getCollection()->map(fn ($c) => $comges->mapCommitment($c))->values()->all()
        ));
    }
}
