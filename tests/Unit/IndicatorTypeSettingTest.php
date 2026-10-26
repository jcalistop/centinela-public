<?php

namespace Tests\Unit;

use App\Enums\IndicatorType\EtapaVigente;
use App\Enums\IndicatorType\TipoEvaluacion;
use App\Models\CenHieDependency;
use App\Models\IndicatorTypeSetting;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndicatorTypeSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_without_tipo_evaluacion_uses_default(): void
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
            'habilitado' => false,
            'etapa_vigente' => EtapaVigente::Cerrado,
            'tipo_evaluacion' => TipoEvaluacion::Anual,
        ]));

        $setting->tipo_evaluacion = null;
        $setting->save();

        $this->assertSame(
            TipoEvaluacion::NoDefinido,
            $setting->fresh()->tipo_evaluacion
        );
    }
}
