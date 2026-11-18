<?php

namespace App\Mcp\Tools;

use App\Enums\Metas\EstadoConvenioEstablecimiento;
use App\Mcp\Concerns\InteractsWithAgentFacade;
use App\Services\Agent\MetFormulacionReadAdapter;
use App\Support\Agent\AgentPagination;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[IsReadOnly]
class ListFormulacionTool extends Tool
{
    use InteractsWithAgentFacade;

    protected string $description = 'Formulación Metas por establecimiento×indicador (convenio, meta, ponderación, fórmula). Solo lectura; filtrado por SS del token. Sin paridad REST.';

    public function handle(Request $request, MetFormulacionReadAdapter $formulacion): Response
    {
        $user = $this->requireAgentUser();
        if ($user instanceof Response) {
            return $user;
        }

        $ssId = (int) $user->cen_hie_dependency_id;
        $details = [];

        $year = $this->applyYearArgument($request, true, $details);
        if ($year instanceof Response) {
            return $year;
        }

        $details['year'] = (int) $year->ano;

        $ley = $request->get('ley');
        $ley = is_string($ley) ? $ley : null;
        $perPage = AgentPagination::perPage($request->get('per_page'));
        $page = AgentPagination::page($request->get('page'));

        if ($ley === null || $ley === '') {
            return $this->jsonError('VALIDATION_ERROR', 'ley es obligatoria (18834|19664|20707).', $details);
        }

        if (! $formulacion->supportsLey($ley)) {
            return $this->jsonOk(AgentPagination::ausente($perPage, ['ley' => $ley]));
        }

        $establecimientoRaw = $request->get('establecimiento_id');
        $establecimientoId = null;
        if ($establecimientoRaw !== null && $establecimientoRaw !== '') {
            if (! is_numeric($establecimientoRaw) || (int) $establecimientoRaw < 1) {
                return $this->jsonError(
                    'VALIDATION_ERROR',
                    'establecimiento_id no es válido.',
                    array_merge($details, ['establecimiento_id' => $establecimientoRaw])
                );
            }

            $establecimientoId = (int) $establecimientoRaw;
            if ($formulacion->findCenterInSs($establecimientoId, $ssId) === null) {
                return $this->jsonOk($formulacion->fueraDeAlcance($perPage, $establecimientoId));
            }
        }

        $estado = $request->get('estado');
        $estado = is_string($estado) ? strtoupper($estado) : null;
        if ($estado !== null && $estado !== '') {
            $allowed = [
                EstadoConvenioEstablecimiento::Borrador->value,
                EstadoConvenioEstablecimiento::Enviado->value,
            ];
            if (! in_array($estado, $allowed, true)) {
                return $this->jsonError(
                    'VALIDATION_ERROR',
                    'estado debe ser BORRADOR o ENVIADO.',
                    array_merge($details, ['estado' => $estado])
                );
            }
        } else {
            $estado = null;
        }

        $aplica = $this->parseOptionalBool($request->get('aplica'));

        try {
            $paginator = $formulacion->paginate(
                $year,
                $ssId,
                $ley,
                $establecimientoId,
                $estado,
                $aplica,
                $perPage,
                $page,
            );

            return $this->jsonOk(AgentPagination::wrap(
                $paginator,
                $paginator->getCollection()->map(fn ($row) => $formulacion->mapRow($row))->values()->all(),
                AgentPagination::EMPTY_REASON_SIN_DATOS
            ));
        } catch (Throwable $e) {
            Log::error('list-formulacion-tool failed', [
                'year' => (int) $year->ano,
                'ley' => $ley,
                'request_id' => request()->attributes->get('agent_request_id'),
                'exception' => $e->getMessage(),
            ]);

            return $this->jsonError(
                'INTERNAL_ERROR',
                'No se pudo obtener la formulación Metas.',
                $details
            );
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'ley' => $schema->string()->enum(['18834', '19664', '20707'])->description('Ley Metas.')->required(),
            'year' => $schema->integer()->description('Año opcional (default del catálogo; no escribe sesión).'),
            'establecimiento_id' => $schema->integer()->description('cen_center_id del SS del token.'),
            'estado' => $schema->string()->enum(['BORRADOR', 'ENVIADO'])->description('Estado del convenio.'),
            'aplica' => $schema->boolean()->description('Filtro opcional de aplicabilidad.'),
            'per_page' => $schema->integer()->description('Tamaño de página (máx. 100).'),
            'page' => $schema->integer()->description('Número de página (1-based).'),
        ];
    }

    private function parseOptionalBool(mixed $raw): ?bool
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_bool($raw)) {
            return $raw;
        }

        if (is_int($raw) || is_float($raw)) {
            return (bool) $raw;
        }

        if (! is_string($raw)) {
            return null;
        }

        $normalized = strtolower(trim($raw));

        return match ($normalized) {
            '1', 'true', 'yes', 'si', 'sí' => true,
            '0', 'false', 'no' => false,
            default => null,
        };
    }
}
