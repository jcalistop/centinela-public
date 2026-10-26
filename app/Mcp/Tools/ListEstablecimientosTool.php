<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithAgentFacade;
use App\Services\Agent\CenCentersReadAdapter;
use App\Support\Agent\AgentPagination;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListEstablecimientosTool extends Tool
{
    use InteractsWithAgentFacade;

    protected string $description = 'Lista establecimientos del SS del token (catálogo + dirección; paginado). Filtros: tipo (SAR|SAPU|SUR o glosa), vigente, estado_funcionamiento, q, comuna.';

    public function handle(Request $request, CenCentersReadAdapter $centers): Response
    {
        $user = $this->requireAgentUser();
        if ($user instanceof Response) {
            return $user;
        }

        $tipoRaw = $request->get('tipo');
        $tipoRaw = is_string($tipoRaw) ? $tipoRaw : null;

        try {
            $tipoGlosa = $centers->resolveTipoFilter($tipoRaw);
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        $q = $request->get('q');
        $q = is_string($q) ? $q : null;
        $comuna = $request->get('comuna');
        $comuna = is_string($comuna) ? $comuna : null;
        $estado = $request->get('estado_funcionamiento');
        $estado = is_string($estado) ? $estado : null;

        $vigente = null;
        $vigenteRaw = $request->get('vigente');
        if ($vigenteRaw !== null && $vigenteRaw !== '') {
            $vigente = filter_var($vigenteRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($vigente === null) {
                $vigente = in_array(strtolower((string) $vigenteRaw), ['1', 'true', 'yes', 'on'], true);
            }
        }

        $page = $centers->paginate(
            (int) $user->cen_hie_dependency_id,
            $tipoGlosa,
            $vigente,
            $estado,
            $q,
            $comuna,
            AgentPagination::perPage($request->get('per_page')),
            AgentPagination::page($request->get('page'))
        );

        return $this->jsonOk(AgentPagination::wrap(
            $page,
            $page->getCollection()->map(fn ($c) => $centers->mapEstablecimiento($c))->values()->all()
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tipo' => $schema->string()->description('Alias SAR|SAPU|SUR o glosa exacta de tipo.'),
            'vigente' => $schema->boolean()->description('Si true, solo estados Vigente en Operación Habitual/Transitoria.'),
            'estado_funcionamiento' => $schema->string()->description('Filtro exacto de estado (alternativa a vigente).'),
            'q' => $schema->string()->description('Busca en código / nombre_corto / nombre.'),
            'comuna' => $schema->string()->description('Filtro por nombre o código de comuna.'),
            'per_page' => $schema->integer()->description('Tamaño de página (máx. 100).'),
            'page' => $schema->integer()->description('Número de página (1-based).'),
        ];
    }
}
