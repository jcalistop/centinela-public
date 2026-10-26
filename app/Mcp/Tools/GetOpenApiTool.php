<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithAgentFacade;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetOpenApiTool extends Tool
{
    use InteractsWithAgentFacade;

    protected string $description = 'Contrato OpenAPI v1 (docs/api/v1/openapi.json), mismo archivo que GET /api/v1/openapi.json.';

    public function handle(Request $request): Response
    {
        $user = $this->requireAgentUser();
        if ($user instanceof Response) {
            return $user;
        }

        $path = base_path('docs/api/v1/openapi.json');
        if (! File::isFile($path)) {
            return Response::error('OpenAPI no publicado.');
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        return $this->jsonOk($payload);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
