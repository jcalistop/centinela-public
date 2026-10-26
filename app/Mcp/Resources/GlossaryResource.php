<?php

namespace App\Mcp\Resources;

use App\Mcp\Concerns\InteractsWithAgentFacade;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;

class GlossaryResource extends Resource
{
    use InteractsWithAgentFacade;

    protected string $uri = 'centinela://v1/glossary';

    protected string $mimeType = 'application/json';

    protected string $description = 'Glosario de dominio versionado (docs/api/v1/glossary.json).';

    public function handle(Request $request): Response
    {
        $user = $this->requireAgentUser();
        if ($user instanceof Response) {
            return $user;
        }

        $path = base_path('docs/api/v1/glossary.json');
        if (! File::isFile($path)) {
            return Response::error('Glosario no publicado.');
        }

        return Response::text(File::get($path));
    }
}
