<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithAgentFacade;
use App\Services\Agent\CenCentersReadAdapter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetEstablecimientoTool extends Tool
{
    use InteractsWithAgentFacade;

    protected string $description = 'Ficha de un establecimiento del SS del token (catálogo + dirección; incluye updated_at y synced_at).';

    public function handle(Request $request, CenCentersReadAdapter $centers): Response
    {
        $user = $this->requireAgentUser();
        if ($user instanceof Response) {
            return $user;
        }

        $id = (int) $request->get('id');
        if ($id < 1) {
            return Response::error('id es obligatorio.');
        }

        $center = $centers->findForSs((int) $user->cen_hie_dependency_id, $id);
        if ($center === null) {
            return Response::error('Establecimiento no encontrado.');
        }

        return $this->jsonOk(['data' => $centers->mapEstablecimiento($center)]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('ID Centinela del establecimiento.')->required(),
        ];
    }
}
