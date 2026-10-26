<?php

namespace App\Mcp\Prompts;

use App\Mcp\Concerns\InteractsWithAgentFacade;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;

class OrientatePrompt extends Prompt
{
    use InteractsWithAgentFacade;

    protected string $name = 'orientate';

    protected string $description = 'Ritual P3a: leer playbook, glossary y capabilities antes de tools de datos.';

    /**
     * @return Response|array<int, Response>
     */
    public function handle(Request $request): Response|array
    {
        $user = $this->requireAgentUser();
        if ($user instanceof Response) {
            return $user;
        }

        $system = <<<'MARKDOWN'
Eres un agente de solo lectura sobre Centinela (P3a / ADR-041).
Antes de listar indicadores o resultados:
1) Lee el resource centinela://v1/agent-playbook (o docs playbook).
2) Lee centinela://v1/glossary (vocabulario).
3) Invoca get-capabilities-tool (ritual + exclusiones) y, si hace falta, get-open-api-tool o centinela://v1/openapi.
4) Solo entonces usa tools de datos.
No uses set_context. No intentes escritura. Ability read únicamente. Respeta el SS del token y pasa year cuando corresponda.
MARKDOWN;

        return [
            Response::text($system)->asAssistant(),
            Response::text('Oriéntame en Centinela y confirma el ritual antes de consultar datos.'),
        ];
    }
}
