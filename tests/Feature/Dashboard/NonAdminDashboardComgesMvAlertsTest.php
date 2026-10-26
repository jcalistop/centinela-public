<?php

namespace Tests\Feature\Dashboard;

use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Dashboard\NonAdminDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NonAdminDashboardComgesMvAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unassigned_referente_does_not_see_pending_mv_alert_on_dashboard(): void
    {
        $fixture = $this->seedDashboardMvFixture();

        $this->actingAs($fixture['other_referente'])
            ->withSession(['indicator_year' => $fixture['year']]);

        Cache::flush();

        $alertas = app(NonAdminDashboardService::class)->forUser($fixture['other_referente'])['alertas'] ?? [];
        $titles = collect($alertas)->pluck('title')->all();

        $this->assertNotContains('Pendiente medios de verificación', $titles);
    }

    public function test_assigned_referente_sees_pending_mv_alert_on_dashboard(): void
    {
        $fixture = $this->seedDashboardMvFixture();

        $this->actingAs($fixture['referente'])
            ->withSession(['indicator_year' => $fixture['year']]);

        Cache::flush();

        $alertas = app(NonAdminDashboardService::class)->forUser($fixture['referente'])['alertas'] ?? [];
        $match = collect($alertas)->firstWhere('title', 'Pendiente medios de verificación');

        $this->assertNotNull($match);
        $this->assertStringContainsString('/admin/indicadores-comges/'.$fixture['indicator_id'], $match['url']);
    }

    /**
     * @return array{year: Year, indicator_id: int, referente: User, other_referente: User}
     */
    private function seedDashboardMvFixture(): array
    {
        if (! Route::has('voyager.indicadores-comges.show')) {
            Route::get('/admin/indicadores-comges/{id}', fn () => '')->name('voyager.indicadores-comges.show');
        }

        $suffix = bin2hex(random_bytes(3));
        $hieId = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-DMV-'.$suffix,
            'nombre' => 'SS Dashboard MV',
            'tipo_dependencia' => 'SERVICIO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $yearId = (int) DB::table('years')->insertGetId([
            'ano' => 2026,
            'default' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $year = Year::findOrFail($yearId);

        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => $hieId,
        ]);
        $referente->role()->associate($referenteRole)->save();
        $referente = $referente->fresh(['role']);

        $other = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => $hieId,
        ]);
        $other->role()->associate($referenteRole)->save();
        $other = $other->fresh(['role']);

        $indicatorTypeId = (int) DB::table('indicator_types')->insertGetId([
            'clave_indicador' => 'comges',
            'nombre_indicador' => 'COMGES',
            'route' => 'voyager.compromisos.index',
            'estado' => 1,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commitmentId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => $indicatorTypeId,
            'clave' => 'C-DMV',
            'nombre' => 'Compromiso dashboard MV',
            'year_id' => $yearId,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentId,
            'clave' => 'I-DMV',
            'nombre' => 'Indicador dashboard MV',
            'ponderacion_indicador' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cutoffId = (int) DB::table('cutoffs')->insertGetId([
            'clave' => 'CORTE I',
            'tipo_corte' => 'TRIMESTRAL',
            'meses' => json_encode([1]),
            'nombre_corte' => 'Corte I',
            'orden' => 1,
            'habilitado' => 1,
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

        DB::table('com_verifiable_documents')->insert([
            'com_action_id' => $actionId,
            'cen_hie_dependency_id' => $hieId,
            'archivos' => '[]',
            'descripcion' => 'MV vacío',
            'verifiable_type' => 'EVALUACION',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $settingId = (int) DB::table('com_indicator_settings')->insertGetId([
            'cen_hie_dependency_id' => $hieId,
            'com_indicator_id' => $indicatorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('com_indicator_setting_user')->insert([
            'com_indicator_setting_id' => $settingId,
            'user_id' => $referente->id,
        ]);

        return [
            'year' => $year,
            'indicator_id' => $indicatorId,
            'referente' => $referente,
            'other_referente' => $other,
        ];
    }
}
