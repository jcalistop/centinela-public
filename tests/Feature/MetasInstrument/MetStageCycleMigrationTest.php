<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\IndicatorType\EtapaVigente;
use App\Models\CenHieDependency;
use App\Models\IndicatorTypeSetting;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MetStageCycleMigrationTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));
    }

    /** @test */
    public function migra_metas_deshabilitado_cerrado_a_no_iniciado(): void
    {
        $metasType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $settingId = DB::table('indicator_type_settings')->insertGetId([
            'indicator_type_id' => $metasType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => EtapaVigente::Cerrado->value,
            'tipo_evaluacion' => 'NO DEFINIDO',
            'habilitado' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require base_path('database/migrations/2026_08_28_160001_migrate_metas_habilitado_cerrado_to_no_iniciado.php');
        $migration->up();

        $this->assertDatabaseHas('indicator_type_settings', [
            'id' => $settingId,
            'etapa_vigente' => EtapaVigente::NoIniciado->value,
        ]);
    }

    /** @test */
    public function migra_falla_si_hay_metas_deshabilitado_con_etapa_distinta_de_cerrado(): void
    {
        $metasType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        DB::table('indicator_type_settings')->insert([
            'indicator_type_id' => $metasType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => EtapaVigente::Autoevaluacion->value,
            'tipo_evaluacion' => 'NO DEFINIDO',
            'habilitado' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);

        $migration = require base_path('database/migrations/2026_08_28_160001_migrate_metas_habilitado_cerrado_to_no_iniciado.php');
        $migration->up();
    }

    /** @test */
    public function nuevo_setting_metas_usa_no_iniciado_como_default(): void
    {
        $metasType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $user = User::factory()->create(['role_id' => $cgRole->id, 'cen_hie_dependency_id' => 1]);
        $user->role()->associate($cgRole)->save();

        $this->actingAs($user)->withSession(['indicator_year' => $this->year]);

        $setting = IndicatorTypeSetting::getData($metasType->id);

        $this->assertSame(EtapaVigente::NoIniciado, $setting->etapa_vigente);
        $this->assertFalse($setting->habilitado);
    }
}
