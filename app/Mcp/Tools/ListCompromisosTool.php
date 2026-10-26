<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithAgentFacade;
use App\Services\Agent\ComgesReadAdapter;
use App\Support\Agent\AgentPagination;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListCompromisosTool extends Tool
{
    use InteractsWithAgentFacade;

    protected string $description = 'Lista secciones COMGES del año paginadas (lectura). Cada fila es una sección del catálogo: clave y nombre son etiquetas opacas (no derivar tipo ni evaluabilidad desde el nombre); indicadores_count informa cuántos indicadores la componen.';

    public function handle(Request $request, ComgesReadAdapter $comges): Response
    {
        $user = $this->requireAgentUser();
        if ($user instanceof Response) {
            return $user;
        }

        $year = $this->applyYearArgument($request);
        if ($year instanceof Response) {
            return $year;
        }

        $q = $request->get('q');
        $q = is_string($q) ? $q : null;
        $page = $comges->paginateCommitments(
            $year,
            $q,
            AgentPagination::perPage($request->get('per_page'))
        );

        return $this->jsonOk(AgentPagination::wrap(
            $page,
            $page->getCollection()->map(fn ($c) => $comges->mapCommitment($c))->values()->all()
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'year' => $schema->integer()->description('Año opcional.'),
            'q' => $schema->string()->description('Filtro texto.'),
            'per_page' => $schema->integer()->description('Tamaño de página (máx. 100).'),
        ];
    }
}
