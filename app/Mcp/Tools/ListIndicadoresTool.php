<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithAgentFacade;
use App\Services\Agent\ComgesReadAdapter;
use App\Services\Agent\MetasReadAdapter;
use App\Support\Agent\AgentPagination;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListIndicadoresTool extends Tool
{
    use InteractsWithAgentFacade;

    protected string $description = 'Lista indicadores neutrales (instrumento=comges|metas). Serializa el catálogo tal cual Centinela: claves y nombres sin normalizar. Un instrumento o ley fuera del catálogo devuelve data vacía con meta.empty_reason=ausente (distinto de sin_datos).';

    public function handle(Request $request, ComgesReadAdapter $comges, MetasReadAdapter $metas): Response
    {
        $user = $this->requireAgentUser();
        if ($user instanceof Response) {
            return $user;
        }

        $year = $this->applyYearArgument($request);
        if ($year instanceof Response) {
            return $year;
        }

        $instrumento = strtolower((string) $request->get('instrumento', ''));
        $q = $request->get('q');
        $q = is_string($q) ? $q : null;
        $perPage = AgentPagination::perPage($request->get('per_page'));

        if ($instrumento === '') {
            return Response::error('Parámetro instrumento requerido: comges|metas.');
        }

        if ($instrumento === ComgesReadAdapter::INSTRUMENTO) {
            $page = $comges->paginateIndicators($year, $q, $perPage);

            return $this->jsonOk(AgentPagination::wrap(
                $page,
                $page->getCollection()->map(fn ($i) => $comges->mapListItem($i))->values()->all(),
                AgentPagination::EMPTY_REASON_SIN_DATOS
            ));
        }

        if ($instrumento === MetasReadAdapter::INSTRUMENTO) {
            $ley = $request->get('ley');
            $ley = is_string($ley) ? $ley : null;

            if (! $metas->supportsLey($ley)) {
                return $this->jsonOk(AgentPagination::ausente($perPage, ['ley' => $ley]));
            }

            $page = $metas->paginateIndicators($year, $ley, $q, $perPage);

            return $this->jsonOk(AgentPagination::wrap(
                $page,
                $page->getCollection()->map(fn ($i) => $metas->mapListItem($i))->values()->all(),
                AgentPagination::EMPTY_REASON_SIN_DATOS
            ));
        }

        return $this->jsonOk(AgentPagination::ausente($perPage, ['instrumento' => $instrumento]));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'instrumento' => $schema->string()->enum(['comges', 'metas'])->description('Instrumento.')->required(),
            'ley' => $schema->string()->description('Ley Metas (18834|19664|20707), opcional.'),
            'year' => $schema->integer()->description('Año opcional.'),
            'q' => $schema->string()->description('Filtro texto.'),
            'per_page' => $schema->integer()->description('Tamaño de página.'),
        ];
    }
}
