<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithAgentFacade;
use App\Services\Dashboard\NonAdminDashboardService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetDashboardAlertasTool extends Tool
{
    use InteractsWithAgentFacade;

    protected string $description = 'Alertas dashboard con año/SS explícitos (misma lógica que GET /api/v1/dashboard/alertas).';

    public function handle(Request $request, NonAdminDashboardService $dashboard): Response
    {
        $user = $this->requireAgentUser();
        if ($user instanceof Response) {
            return $user;
        }

        $year = $this->applyYearArgument($request);
        if ($year instanceof Response) {
            return $year;
        }

        $ssId = (int) $user->cen_hie_dependency_id;
        $alerts = $dashboard->alertsFor($user, $year, $ssId);
        $evaluation = $dashboard->alertsEvaluationFor($user, $year, $ssId, $alerts);

        return $this->jsonOk([
            'data' => $alerts,
            'meta' => [
                'year' => $year->ano,
                'cen_hie_dependency_id' => $ssId,
                'count' => count($alerts),
                'last_evaluated_at' => $evaluation['last_evaluated_at'],
                'reglas_evaluadas' => $evaluation['reglas_evaluadas'],
            ],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'year' => $schema->integer()->description('Año opcional.'),
        ];
    }
}
