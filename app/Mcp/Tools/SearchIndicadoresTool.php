<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithAgentFacade;
use App\Services\Agent\AgentSearchService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class SearchIndicadoresTool extends Tool
{
    use InteractsWithAgentFacade;

    protected string $description = 'Búsqueda unificada de indicadores COMGES+Metas (sin exigir instrumento).';

    public function handle(Request $request, AgentSearchService $search): Response
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
        $result = $search->search($year, is_string($q) ? $q : null, $request->get('per_page'));

        if (! $result['ok']) {
            return Response::error($result['message']);
        }

        return $this->jsonOk($result['payload']);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string()->description('Texto a buscar (mín. 2 caracteres).')->required(),
            'year' => $schema->integer()->description('Año (override opcional).'),
            'per_page' => $schema->integer()->description('Tope total de hits (default 25, máx. 100).'),
        ];
    }
}
