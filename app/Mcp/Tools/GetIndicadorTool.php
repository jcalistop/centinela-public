<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithAgentFacade;
use App\Services\Agent\ComgesReadAdapter;
use App\Services\Agent\MetasReadAdapter;
use App\Support\Agent\AgentPagination;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetIndicadorTool extends Tool
{
    use InteractsWithAgentFacade;

    protected string $description = 'Detalle de un indicador (instrumento + id). Serializa el catálogo tal cual Centinela (clave y nombre sin normalizar; no certifica vigencia OOTT). Un instrumento fuera del catálogo devuelve data vacía con meta.empty_reason=ausente.';

    public function handle(Request $request, ComgesReadAdapter $comges, MetasReadAdapter $metas): Response
    {
        $user = $this->requireAgentUser();
        if ($user instanceof Response) {
            return $user;
        }

        $year = $this->applyYearArgument($request);
        if ($year instanceof Response) {
            return $year;
        }

        $id = (int) $request->get('id');
        if ($id < 1) {
            return Response::error('id de indicador inválido.');
        }

        $instrumento = strtolower((string) $request->get('instrumento', ''));

        if ($instrumento === '') {
            return Response::error('Parámetro instrumento requerido: comges|metas.');
        }

        if ($instrumento === ComgesReadAdapter::INSTRUMENTO) {
            $ssId = (int) $user->cen_hie_dependency_id;
            $indicator = $comges->findIndicator($year, $id, $ssId);
            if ($indicator === null) {
                return Response::error('Indicador COMGES no encontrado.');
            }

            return $this->jsonOk(['data' => $comges->mapDetail($indicator, $year, $ssId)]);
        }

        if ($instrumento === MetasReadAdapter::INSTRUMENTO) {
            $indicator = $metas->findIndicator($year, $id);
            if ($indicator === null) {
                return Response::error('Indicador Metas no encontrado.');
            }

            return $this->jsonOk(['data' => $metas->mapDetail($indicator)]);
        }

        return $this->jsonOk([
            'data' => null,
            'meta' => [
                'instrumento' => $instrumento,
                'empty_reason' => AgentPagination::EMPTY_REASON_AUSENTE,
            ],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'instrumento' => $schema->string()->enum(['comges', 'metas'])->required(),
            'id' => $schema->integer()->description('ID del indicador.')->required(),
            'year' => $schema->integer()->description('Año opcional.'),
        ];
    }
}
