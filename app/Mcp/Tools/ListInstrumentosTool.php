<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithAgentFacade;
use App\Services\Agent\ComgesReadAdapter;
use App\Services\Agent\MetasReadAdapter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListInstrumentosTool extends Tool
{
    use InteractsWithAgentFacade;

    protected string $description = 'Instrumentos presentes en la fachada neutral (comges, metas). Lo no listado no está en Centinela: consultarlo devuelve data vacía con meta.empty_reason=ausente, no un error ni datos de otro instrumento.';

    public function handle(Request $request, MetasReadAdapter $metas): Response
    {
        $user = $this->requireAgentUser();
        if ($user instanceof Response) {
            return $user;
        }

        return $this->jsonOk([
            'data' => [
                [
                    'code' => ComgesReadAdapter::INSTRUMENTO,
                    'label' => 'Compromisos de Gestión (COMGES)',
                    'leyes' => [],
                ],
                [
                    'code' => MetasReadAdapter::INSTRUMENTO,
                    'label' => 'Metas sanitarias',
                    'leyes' => $metas->availableLeyes()->all(),
                ],
            ],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
