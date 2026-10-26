<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithAgentFacade;
use App\Services\Agent\MetasReadAdapter;
use App\Support\Agent\AgentPagination;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[IsReadOnly]
class ListResultadosTool extends Tool
{
    use InteractsWithAgentFacade;

    protected string $description = 'Resultados Metas por establecimiento (filtrados por SS del token; paginado).';

    public function handle(Request $request, MetasReadAdapter $metas): Response
    {
        $user = $this->requireAgentUser();
        if ($user instanceof Response) {
            return $user;
        }

        $indicatorId = (int) $request->get('indicador_id');
        $details = ['indicador_id' => $indicatorId > 0 ? $indicatorId : $request->get('indicador_id')];

        $year = $this->applyYearArgument($request, true, $details);
        if ($year instanceof Response) {
            return $year;
        }

        $details['year'] = (int) $year->ano;

        if ($indicatorId < 1) {
            return $this->jsonError('VALIDATION_ERROR', 'indicador_id es obligatorio.', $details);
        }

        if ($metas->findIndicator($year, $indicatorId) === null) {
            return $this->jsonError('NOT_FOUND', 'Indicador Metas no encontrado.', $details);
        }

        try {
            $page = $metas->paginateResults(
                $year,
                (int) $user->cen_hie_dependency_id,
                $indicatorId,
                AgentPagination::perPage($request->get('per_page')),
                AgentPagination::page($request->get('page'))
            );

            return $this->jsonOk(AgentPagination::wrap(
                $page,
                $page->getCollection()->map(fn ($r) => $metas->mapResult($r))->values()->all()
            ));
        } catch (Throwable $e) {
            Log::error('list-resultados-tool failed', [
                'indicador_id' => $indicatorId,
                'year' => (int) $year->ano,
                'request_id' => request()->attributes->get('agent_request_id'),
                'exception' => $e->getMessage(),
            ]);

            return $this->jsonError(
                'INTERNAL_ERROR',
                'No se pudieron obtener los resultados del indicador.',
                $details
            );
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'indicador_id' => $schema->integer()->required(),
            'year' => $schema->integer()->description('Año opcional.'),
            'per_page' => $schema->integer()->description('Tamaño de página (máx. 100).'),
            'page' => $schema->integer()->description('Número de página (1-based).'),
        ];
    }
}
