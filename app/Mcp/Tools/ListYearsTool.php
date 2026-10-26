<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithAgentFacade;
use App\Models\Year;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListYearsTool extends Tool
{
    use InteractsWithAgentFacade;

    protected string $description = 'Lista años del catálogo Centinela.';

    public function handle(Request $request): Response
    {
        $user = $this->requireAgentUser();
        if ($user instanceof Response) {
            return $user;
        }

        $years = Year::query()
            ->orderByDesc('ano')
            ->get(['id', 'ano', 'default'])
            ->map(fn (Year $y) => [
                'id' => $y->id,
                'ano' => $y->ano,
                'default' => (bool) $y->default,
            ])
            ->values()
            ->all();

        return $this->jsonOk(['data' => $years]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
