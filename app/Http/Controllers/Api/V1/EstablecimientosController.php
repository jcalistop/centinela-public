<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Agent\CenCentersReadAdapter;
use App\Support\Agent\AgentErrorEnvelope;
use App\Support\Agent\AgentPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class EstablecimientosController extends Controller
{
    public function index(Request $request, CenCentersReadAdapter $centers): JsonResponse
    {
        $user = Auth::user();
        if (! $user instanceof User || $user->cen_hie_dependency_id === null) {
            return AgentErrorEnvelope::json('UNAUTHENTICATED', 'Se requiere usuario agente con SS fijo.', 401);
        }

        try {
            $tipoGlosa = $centers->resolveTipoFilter(
                is_string($request->query('tipo')) ? $request->query('tipo') : null
            );
        } catch (InvalidArgumentException $e) {
            return AgentErrorEnvelope::json('VALIDATION_ERROR', $e->getMessage(), 422, [
                'tipo' => $request->query('tipo'),
            ]);
        }

        $q = $request->query('q');
        $q = is_string($q) ? $q : null;
        $comuna = $request->query('comuna');
        $comuna = is_string($comuna) ? $comuna : null;
        $estado = $request->query('estado_funcionamiento');
        $estado = is_string($estado) ? $estado : null;

        $page = $centers->paginate(
            (int) $user->cen_hie_dependency_id,
            $tipoGlosa,
            $this->parseVigente($request->query('vigente')),
            $estado,
            $q,
            $comuna,
            AgentPagination::perPage($request->query('per_page')),
            AgentPagination::page($request->query('page'))
        );

        return response()->json(AgentPagination::wrap(
            $page,
            $page->getCollection()->map(fn ($c) => $centers->mapEstablecimiento($c))->values()->all()
        ));
    }

    public function show(int $id, CenCentersReadAdapter $centers): JsonResponse
    {
        $user = Auth::user();
        if (! $user instanceof User || $user->cen_hie_dependency_id === null) {
            return AgentErrorEnvelope::json('UNAUTHENTICATED', 'Se requiere usuario agente con SS fijo.', 401);
        }

        $center = $centers->findForSs((int) $user->cen_hie_dependency_id, $id);
        if ($center === null) {
            return AgentErrorEnvelope::json('NOT_FOUND', 'Establecimiento no encontrado.', 404);
        }

        return response()->json(['data' => $centers->mapEstablecimiento($center)]);
    }

    private function parseVigente(mixed $raw): ?bool
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_bool($raw)) {
            return $raw;
        }

        $normalized = strtolower((string) $raw);

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}
