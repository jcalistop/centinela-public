<?php

namespace Tests\Feature;

use App\Models\CenHieDependency;
use App\Models\IndicatorTypeSetting;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MigrateEtapaVigenteAnualToCerradoTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_convierte_etapa_anual_a_cerrado(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $setting = IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $indicatorType->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'CERRADO',
            'habilitado' => false,
        ]));

        DB::table('indicator_type_settings')
            ->where('id', $setting->id)
            ->update(['etapa_vigente' => 'ANUAL']);

        $migration = require database_path('migrations/2026_05_20_120000_migrate_etapa_vigente_anual_to_cerrado.php');
        $migration->up();

        $this->assertSame(
            'CERRADO',
            DB::table('indicator_type_settings')->where('id', $setting->id)->value('etapa_vigente')
        );
        $this->assertSame(0, DB::table('indicator_type_settings')->where('etapa_vigente', 'ANUAL')->count());
    }
}
