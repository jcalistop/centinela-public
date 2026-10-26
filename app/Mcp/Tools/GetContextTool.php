<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithAgentFacade;
use App\Models\CenHieDependency;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetContextTool extends Tool
{
    use InteractsWithAgentFacade;

    protected string $description = 'Contexto año/SS del usuario agente (sin set_context de sesión).';

    public function handle(Request $request): Response
    {
        $user = $this->requireAgentUser();
        if ($user instanceof Response) {
            return $user;
        }

        $year = $this->applyYearArgument($request);
        if ($year instanceof Response) {
            return $year;
        }

        $ssId = (int) $user->cen_hie_dependency_id;
        $ss = CenHieDependency::query()->find($ssId);

        return $this->jsonOk([
            'year' => $year->ano,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => $ssId,
            'ss_nombre' => $ss?->nombre,
            'user_id' => $user->id,
            'abilities' => ['read'],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'year' => $schema->integer()->description('Año opcional de override.'),
        ];
    }
}
