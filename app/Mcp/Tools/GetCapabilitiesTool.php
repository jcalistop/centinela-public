<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithAgentFacade;
use App\Mcp\Servers\CentinelaAgentServer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Transport\FakeTransporter;

#[IsReadOnly]
class GetCapabilitiesTool extends Tool
{
    use InteractsWithAgentFacade;

    protected string $description = 'Capacidades P3a: ritual de orientación, tools registradas y exclusiones (sin escritura ni set_context).';

    /**
     * @var list<string>
     */
    private const RITUAL = [
        'get-health-tool',
        'list-years-tool',
        'get-context-tool',
        'list-instrumentos-tool',
        'get-glossary-tool',
        'get-capabilities-tool',
        'get-open-api-tool',
        'search-indicadores-tool',
        'list-compromisos-tool|list-indicadores-tool|get-indicador-tool|list-resultados-tool|get-dashboard-alertas-tool',
    ];

    public function handle(Request $request): Response
    {
        $user = $this->requireAgentUser();
        if ($user instanceof Response) {
            return $user;
        }

        $server = new CentinelaAgentServer(new FakeTransporter);
        $tools = $server->createContext()->tools()->map(fn (Tool $tool): array => [
            'name' => $tool->name(),
            'description' => $tool->description(),
        ])->values()->all();

        return $this->jsonOk([
            'surface' => 'mcp',
            'ability' => 'read',
            'ritual' => self::RITUAL,
            'tools' => $tools,
            'excluded' => [
                'write',
                'write:*',
                'set_context',
                'voyager_scrape',
                'chat_ia',
            ],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
