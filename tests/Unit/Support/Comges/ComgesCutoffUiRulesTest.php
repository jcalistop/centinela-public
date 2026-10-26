<?php

namespace Tests\Unit\Support\Comges;

use App\Enums\Corte\CutoffClave;
use App\Models\ComIndicatorCutoff;
use App\Support\Comges\ComgesCutoffUiRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ComgesCutoffUiRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_evaluation_documents_for_first_and_second_monitoring_and_annual_cutoffs(): void
    {
        foreach ([
            ['clave' => CutoffClave::CorteI, 'nombre' => '1er Monitoreo'],
            ['clave' => CutoffClave::CorteII, 'nombre' => '2° Monitoreo'],
            ['clave' => CutoffClave::CorteUnico, 'nombre' => 'Corte Anual'],
        ] as $definition) {
            $cutoff = $this->makeIndicatorCutoff($definition['clave'], $definition['nombre'], evaluacionLocal: false);

            $this->assertTrue(
                ComgesCutoffUiRules::showsVerifiableEvaluationDocuments($cutoff),
                'Debe mostrar medios de verificación para '.$definition['nombre'],
            );
        }
    }

    public function test_hides_evaluation_documents_for_apelacion_and_evaluacion_local_only_cutoffs(): void
    {
        $apelacion = $this->makeIndicatorCutoff(CutoffClave::Apelacion, 'Apelación', evaluacionLocal: false);
        $evaluacionLocal = $this->makeIndicatorCutoff(CutoffClave::CorteIII, 'Seguimiento local', evaluacionLocal: true);

        $this->assertFalse(ComgesCutoffUiRules::showsVerifiableEvaluationDocuments($apelacion));
        $this->assertFalse(ComgesCutoffUiRules::showsVerifiableEvaluationDocuments($evaluacionLocal));
    }

    public function test_shows_appeal_documents_only_when_evaluacion_local_is_false(): void
    {
        $withAppeal = $this->makeIndicatorCutoff(CutoffClave::CorteI, '1er Monitoreo', evaluacionLocal: false);
        $withoutAppeal = $this->makeIndicatorCutoff(CutoffClave::CorteI, '1er Monitoreo', evaluacionLocal: true);

        $this->assertTrue(ComgesCutoffUiRules::showsVerifiableAppealDocuments($withAppeal));
        $this->assertFalse(ComgesCutoffUiRules::showsVerifiableAppealDocuments($withoutAppeal));
    }

    private function makeIndicatorCutoff(CutoffClave $clave, string $nombreCorte, bool $evaluacionLocal): ComIndicatorCutoff
    {
        $cutoffId = (int) DB::table('cutoffs')->insertGetId([
            'clave' => $clave->value,
            'tipo_corte' => 'TRIMESTRAL',
            'meses' => json_encode([1]),
            'nombre_corte' => $nombreCorte,
            'orden' => 1,
            'habilitado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $this->seedCommitment(),
            'clave' => 'IND-'.bin2hex(random_bytes(2)),
            'nombre' => 'Indicador',
            'ponderacion_indicador' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorCutoffId = (int) DB::table('com_indicator_cutoffs')->insertGetId([
            'com_indicator_id' => $indicatorId,
            'cutoff_id' => $cutoffId,
            'ponderacion_corte' => 100,
            'evaluacion_local' => $evaluacionLocal ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ComIndicatorCutoff::query()
            ->with('cutoff')
            ->findOrFail($indicatorCutoffId);
    }

    private function seedCommitment(): int
    {
        if (! DB::table('indicator_types')->where('id', 1)->exists()) {
            DB::table('indicator_types')->insert([
                'id' => 1,
                'clave_indicador' => 'comges',
                'nombre_indicador' => 'COMGES',
                'route' => 'voyager.compromisos.index',
                'estado' => 1,
                'orden' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $yearId = (int) DB::table('years')->insertGetId([
            'ano' => 2026,
            'default' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => 1,
            'clave' => 'C-UI-RULES',
            'nombre' => 'Compromiso',
            'year_id' => $yearId,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
