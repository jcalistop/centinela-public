<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Agent\ComgesReadAdapter;
use App\Services\Agent\MetasReadAdapter;
use App\Support\Agent\AgentErrorEnvelope;
use App\Support\Agent\AgentPagination;
use App\Support\Agent\AgentRequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IndicadoresController extends Controller
{
    public function index(
        Request $request,
        AgentRequestContext $context,
        ComgesReadAdapter $comges,
        MetasReadAdapter $metas,
    ): JsonResponse {
        $instrumento = strtolower((string) $request->query('instrumento', ''));
        $q = $request->query('q');
        $q = is_string($q) ? $q : null;
        $perPage = AgentPagination::perPage($request->query('per_page'));
        $year = $context->year();

        if ($instrumento === ComgesReadAdapter::INSTRUMENTO) {
            $page = $comges->paginateIndicators($year, $q, $perPage);

            return response()->json(AgentPagination::wrap(
                $page,
                $page->getCollection()->map(fn ($i) => $comges->mapListItem($i))->values()->all(),
                AgentPagination::EMPTY_REASON_SIN_DATOS
            ));
        }

        if ($instrumento === MetasReadAdapter::INSTRUMENTO) {
            $ley = $request->query('ley');
            $ley = is_string($ley) ? $ley : null;

            if (! $metas->supportsLey($ley)) {
                return response()->json(AgentPagination::ausente($perPage, ['ley' => $ley]));
            }

            $page = $metas->paginateIndicators($year, $ley, $q, $perPage);

            return response()->json(AgentPagination::wrap(
                $page,
                $page->getCollection()->map(fn ($i) => $metas->mapListItem($i))->values()->all(),
                AgentPagination::EMPTY_REASON_SIN_DATOS
            ));
        }

        return response()->json(AgentPagination::ausente($perPage, ['instrumento' => $instrumento]));
    }

    public function show(
        Request $request,
        int $id,
        AgentRequestContext $context,
        ComgesReadAdapter $comges,
        MetasReadAdapter $metas,
    ): JsonResponse {
        $instrumento = strtolower((string) $request->query('instrumento', ''));
        $year = $context->year();

        if ($instrumento === ComgesReadAdapter::INSTRUMENTO) {
            $ssId = $context->ssId($request);
            $indicator = $comges->findIndicator($year, $id, $ssId);
            if ($indicator === null) {
                return AgentErrorEnvelope::json('NOT_FOUND', 'Indicador COMGES no encontrado.', 404);
            }

            return response()->json(['data' => $comges->mapDetail($indicator, $year, $ssId)]);
        }

        if ($instrumento === MetasReadAdapter::INSTRUMENTO) {
            $indicator = $metas->findIndicator($year, $id);
            if ($indicator === null) {
                return AgentErrorEnvelope::json('NOT_FOUND', 'Indicador Metas no encontrado.', 404);
            }

            return response()->json(['data' => $metas->mapDetail($indicator)]);
        }

        return response()->json([
            'data' => null,
            'meta' => [
                'instrumento' => $instrumento,
                'empty_reason' => AgentPagination::EMPTY_REASON_AUSENTE,
            ],
        ]);
    }
}
