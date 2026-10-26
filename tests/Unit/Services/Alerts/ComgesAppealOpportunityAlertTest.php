<?php

namespace Tests\Unit\Services\Alerts;

use App\Enums\IndicatorType\EtapaVigente;
use App\Models\ComCommitment;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Alerts\CentinelaVisualAlertService;
use App\Services\Alerts\Providers\ComgesCommitmentBrowseProvider;
use App\Support\Alerts\AlertContext;
use App\Support\Alerts\AlertSurface;
use App\Support\Alerts\VisualAlertPlacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ComgesAppealOpportunityAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_r7_emitted_only_in_preliminar_etapa(): void
    {
        $data = $this->seedCutoffWithScores(auto: 3.5, preliminar: 2.0);
        $user = $this->referenteUser($data['hie_id']);
        $year = Year::find($data['year_id']);

        $commitment = ComCommitment::query()
            ->with([
                'comIndicators.comIndicatorCutoffs.cutoff',
                'comIndicators.comIndicatorCutoffs.comActions.comEvaluations',
            ])
            ->findOrFail($data['commitment_id']);

        $service = new CentinelaVisualAlertService;
        $service->register(new ComgesCommitmentBrowseProvider);

        $contextPreliminar = new AlertContext(
            user: $user,
            year: $year,
            surface: AlertSurface::Browse,
            usesScoreScale: true,
            cenHieDependencyId: $data['hie_id'],
            comgesEtapaVigente: EtapaVigente::Preliminar,
        );

        $alerts = $service->collectForModule($contextPreliminar, 'comges', collect([$commitment]));

        $this->assertTrue(
            $alerts->contains(
                fn ($a) => $a->placement === VisualAlertPlacement::EvaluationPreliminar
                    && str_contains($a->title, 'Oportunidad de apelación')
            )
        );

        $contextAuto = new AlertContext(
            user: $user,
            year: $year,
            surface: AlertSurface::Browse,
            usesScoreScale: true,
            cenHieDependencyId: $data['hie_id'],
            comgesEtapaVigente: EtapaVigente::Autoevaluacion,
        );

        $alertsAuto = $service->collectForModule($contextAuto, 'comges', collect([$commitment]));

        $this->assertFalse(
            $alertsAuto->contains(
                fn ($a) => str_contains($a->title, 'Oportunidad de apelación')
            )
        );
    }

    /**
     * @return array{commitment_id: int, year_id: int, hie_id: int}
     */
    private function seedCutoffWithScores(float $auto, float $preliminar): array
    {
        $suffix = bin2hex(random_bytes(4));
        $hieId = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-'.$suffix,
            'nombre' => 'SS',
            'tipo_dependencia' => 'SERVICIO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $yearId = (int) DB::table('years')->insertGetId([
            'ano' => 2025,
            'default' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorTypeId = (int) DB::table('indicator_types')->insertGetId([
            'clave_indicador' => 'cg-'.$suffix,
            'nombre_indicador' => 'COMGES',
            'route' => 'comp-'.$suffix,
            'estado' => 1,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cutoffId = (int) DB::table('cutoffs')->insertGetId([
            'clave' => 'CORTE I',
            'tipo_corte' => 'TRIMESTRAL',
            'meses' => json_encode([1]),
            'nombre_corte' => 'Corte',
            'orden' => 1,
            'habilitado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commitmentId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => $indicatorTypeId,
            'clave' => 'C1',
            'nombre' => 'Comp',
            'year_id' => $yearId,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentId,
            'clave' => 'I1',
            'nombre' => 'Ind',
            'ponderacion_indicador' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $linkId = (int) DB::table('com_indicator_cutoffs')->insertGetId([
            'com_indicator_id' => $indicatorId,
            'cutoff_id' => $cutoffId,
            'ponderacion_corte' => 100,
            'evaluacion_local' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $actionId = (int) DB::table('com_actions')->insertGetId([
            'com_indicator_cutoff_id' => $linkId,
            'numero' => '1',
            'ponderacion_accion' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['AUTOEVALUACION' => $auto, 'PRELIMINAR' => $preliminar] as $tipo => $score) {
            DB::table('com_evaluations')->insert([
                'com_action_id' => $actionId,
                'cen_hie_dependency_id' => $hieId,
                'tipo_evaluacion' => $tipo,
                'numerador' => $score,
                'denominador' => 4,
                'resultado' => $score,
                'cumplimiento' => $score,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'commitment_id' => $commitmentId,
            'year_id' => $yearId,
            'hie_id' => $hieId,
        ];
    }

    private function referenteUser(int $hieId): User
    {
        $role = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);

        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => $hieId,
        ]);
        $user->role()->associate($role)->save();

        return $user;
    }
}
