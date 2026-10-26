<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\OrientatePrompt;
use App\Mcp\Resources\AgentPlaybookResource;
use App\Mcp\Resources\GlossaryResource;
use App\Mcp\Resources\OpenApiResource;
use App\Mcp\Tools\GetCapabilitiesTool;
use App\Mcp\Tools\GetContextTool;
use App\Mcp\Tools\GetDashboardAlertasTool;
use App\Mcp\Tools\GetEstablecimientoTool;
use App\Mcp\Tools\GetGlossaryTool;
use App\Mcp\Tools\GetHealthTool;
use App\Mcp\Tools\GetIndicadorTool;
use App\Mcp\Tools\GetOpenApiTool;
use App\Mcp\Tools\ListCompromisosTool;
use App\Mcp\Tools\ListEstablecimientosTool;
use App\Mcp\Tools\ListIndicadoresTool;
use App\Mcp\Tools\ListInstrumentosTool;
use App\Mcp\Tools\ListResultadosTool;
use App\Mcp\Tools\ListYearsTool;
use App\Mcp\Tools\SearchIndicadoresTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

class CentinelaAgentServer extends Server
{
    protected string $name = 'Centinela Agent Read';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        Servidor MCP de **solo lectura** (P3a / ADR-041). Reutiliza la fachada neutral `/api/v1`.

        Ritual sugerido antes de pedir datos:
        1) get-health-tool
        2) list-years-tool / get-context-tool
        3) list-instrumentos-tool
        4) get-glossary-tool
        5) get-capabilities-tool / get-open-api-tool
        6) search-indicadores-tool (descubrimiento sin instrumento) o list-indicadores-tool
        7) list-establecimientos-tool / get-establecimiento-tool (maestro CEN del SS)
        8) detallar / resultados / alertas

        Resources: centinela://v1/glossary, centinela://v1/openapi, centinela://v1/agent-playbook.
        Prompt: orientate.

        Pasa `year` en argumentos cuando necesites override de año (indicadores/resultados).
        Establecimientos: territorio = SS del token; filtros tipo (SAR|SAPU|SUR) y vigente.
        No existe tool `set_context`. No hay tools de escritura (solo ability `read`).
    MARKDOWN;

    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        GetHealthTool::class,
        ListYearsTool::class,
        GetContextTool::class,
        ListInstrumentosTool::class,
        GetGlossaryTool::class,
        GetCapabilitiesTool::class,
        GetOpenApiTool::class,
        SearchIndicadoresTool::class,
        ListCompromisosTool::class,
        ListIndicadoresTool::class,
        GetIndicadorTool::class,
        ListEstablecimientosTool::class,
        GetEstablecimientoTool::class,
        ListResultadosTool::class,
        GetDashboardAlertasTool::class,
    ];

    /**
     * @var array<int, class-string<Server\Resource>>
     */
    protected array $resources = [
        GlossaryResource::class,
        OpenApiResource::class,
        AgentPlaybookResource::class,
    ];

    /**
     * @var array<int, class-string<Prompt>>
     */
    protected array $prompts = [
        OrientatePrompt::class,
    ];
}
