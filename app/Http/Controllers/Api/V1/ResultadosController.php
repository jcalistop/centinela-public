<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Agent\MetasReadAdapter;
use App\Support\Agent\AgentErrorEnvelope;
use App\Support\Agent\AgentPagination;
use App\Support\Agent\AgentRequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResultadosController extends Controller
{
    public function __invoke(
        Request $request,
        AgentRequestContext $context,
        MetasReadAdapter $metas,
    ): JsonResponse {
        $instrumento = strtolower((string) $request->query('instrumento', MetasReadAdapter::INSTRUMENTO));
        if ($instrumento !== MetasReadAdapter::INSTRUMENTO) {
            return AgentErrorEnvelope::json(
                'VALIDATION_ERROR',
                'Resultados paginados MVP solo para instrumento=metas.',
                422
            );
        }

        $indicatorId = filter_var($request->query('indicador_id'), FILTER_VALIDATE_INT);
        if ($indicatorId === false || $indicatorId < 1) {
            return AgentErrorEnvelope::json(
                'VALIDATION_ERROR',
                'indicador_id es obligatorio.',
                422
            );
        }

        try {
            $ssId = $context->ssId($request);
        } catch (\RuntimeException $e) {
            return AgentErrorEnvelope::json('FORBIDDEN', $e->getMessage(), 403);
        }

        $year = $context->year();
        if ($metas->findIndicator($year, $indicatorId) === null) {
            return AgentErrorEnvelope::json('NOT_FOUND', 'Indicador Metas no encontrado.', 404);
        }

        $page = $metas->paginateResults(
            $year,
            $ssId,
            $indicatorId,
            AgentPagination::perPage($request->query('per_page'), 25, 100),
            AgentPagination::page($request->query('page'))
        );

        return response()->json(AgentPagination::wrap(
            $page,
            $page->getCollection()->map(fn ($r) => $metas->mapResult($r))->values()->all()
        ));
    }
}
