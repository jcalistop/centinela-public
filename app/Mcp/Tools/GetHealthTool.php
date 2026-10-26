<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithAgentFacade;
use App\Services\Agent\AgentCoverageService;
use App\Support\CentinelaSession;
use App\Support\YearContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetHealthTool extends Tool
{
    use InteractsWithAgentFacade;

    protected string $description = <<<'MARKDOWN'
        Health de la superficie agentes (versión, flag, año efectivo / default, cobertura del catálogo del año por instrumento y ley).
    MARKDOWN;

    public function handle(Request $request, AgentCoverageService $coverage): Response
    {
        $user = $this->requireAgentUser();
        if ($user instanceof Response) {
            return $user;
        }

        $year = $this->applyYearArgument($request);
        if ($year instanceof Response) {
            return $year;
        }

        $default = app(YearContext::class)->resolveDefaultYear();

        return $this->jsonOk([
            'status' => 'ok',
            'surface' => 'mcp',
            'version' => CentinelaSession::resolveDeployedVersionLabel(),
            'agent_api_enabled' => (bool) config('centinela.agent_api.enabled', false),
            'year' => $year->ano,
            'default_year' => $default->ano,
            'cobertura' => $coverage->forYear($year),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'year' => $schema->integer()
                ->description('Año de catálogo (opcional; default del sistema si se omite).'),
        ];
    }
}
