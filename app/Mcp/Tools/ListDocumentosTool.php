<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithAgentFacade;
use App\Services\Agent\DocumentsReadAdapter;
use App\Support\Agent\AgentPagination;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[IsReadOnly]
class ListDocumentosTool extends Tool
{
    use InteractsWithAgentFacade;

    protected string $description = 'Lista documentos normativos del SS del token (año × instrumento/ley). Solo lectura; sin filtro por establecimiento ni hash persistido.';

    public function handle(Request $request, DocumentsReadAdapter $documents): Response
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
        $instrumento = $request->get('instrumento');
        $instrumento = is_string($instrumento) ? $instrumento : null;
        $perPage = AgentPagination::perPage($request->get('per_page'));
        $page = AgentPagination::page($request->get('page'));

        $filter = $documents->resolveTypeFilter($ley, $instrumento);
        if ($filter['ok'] === false) {
            $echo = array_filter([
                'ley' => ($ley !== null && $ley !== '') ? $ley : null,
                'instrumento' => ($instrumento !== null && $instrumento !== '') ? $instrumento : null,
            ], fn ($v) => $v !== null);

            return $this->jsonOk(AgentPagination::ausente($perPage, $echo));
        }

        try {
            $paginator = $documents->paginate(
                $year,
                $ssId,
                $filter['type_ids'],
                $perPage,
                $page,
            );

            return $this->jsonOk(AgentPagination::wrap(
                $paginator,
                $paginator->getCollection()->map(fn ($row) => $documents->mapDocument($row))->values()->all(),
                AgentPagination::EMPTY_REASON_SIN_DATOS
            ));
        } catch (Throwable $e) {
            Log::error('list-documentos-tool failed', [
                'year' => (int) $year->ano,
                'request_id' => request()->attributes->get('agent_request_id'),
                'exception' => $e->getMessage(),
            ]);

            return $this->jsonError(
                'INTERNAL_ERROR',
                'No se pudo listar documentos.',
                $details
            );
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'year' => $schema->integer()->description('Año opcional (default del catálogo; no escribe sesión).'),
            'ley' => $schema->string()->enum(['18834', '19664', '20707'])->description('Ley Metas (filtra indicator_type).'),
            'instrumento' => $schema->string()->enum(['comges', 'metas', 'adp'])->description('Instrumento del catálogo.'),
            'per_page' => $schema->integer()->description('Tamaño de página (máx. 100).'),
            'page' => $schema->integer()->description('Número de página (1-based).'),
        ];
    }
}
