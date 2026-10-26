<?php

namespace App\Mcp\Resources;

use App\Mcp\Concerns\InteractsWithAgentFacade;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;

class AgentPlaybookResource extends Resource
{
    use InteractsWithAgentFacade;

    protected string $uri = 'centinela://v1/agent-playbook';

    protected string $mimeType = 'text/markdown';

    protected string $description = 'Playbook corto de orientación para agentes P3a (solo lectura).';

    public function handle(Request $request): Response
    {
        $user = $this->requireAgentUser();
        if ($user instanceof Response) {
            return $user;
        }

        $path = base_path('docs/api/v1/agent-playbook.md');
        if (! File::isFile($path)) {
            return Response::error('Playbook de agente no publicado.');
        }

        return Response::text(File::get($path));
    }
}
