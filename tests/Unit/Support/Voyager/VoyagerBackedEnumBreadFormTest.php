<?php

namespace Tests\Unit\Support\Voyager;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Enums\Cen\CentroNivelAtencion;
use App\Enums\Corte\CutoffClave;
use App\Enums\Corte\CutoffTipo;
use App\Enums\IndicatorType\EtapaVigente;
use App\Enums\IndicatorType\TipoEvaluacion;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\Cutoff;
use App\Models\IndicatorTypeSetting;
use App\Models\Year;
use App\Support\Voyager\VoyagerBackedEnumBreadForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMinimalCenCenter;
use Tests\TestCase;

class VoyagerBackedEnumBreadFormTest extends TestCase
{
    use CreatesMinimalCenCenter;
    use RefreshDatabase;

    public function test_expose_string_values_for_voyager_radio_comparison(): void
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
            'habilitado' => true,
            'etapa_vigente' => EtapaVigente::Autoevaluacion,
            'tipo_evaluacion' => TipoEvaluacion::Trimestral,
        ]));

        $setting = $setting->fresh();

        $this->assertInstanceOf(TipoEvaluacion::class, $setting->tipo_evaluacion);

        VoyagerBackedEnumBreadForm::exposeStringValues($setting, ['tipo_evaluacion', 'etapa_vigente']);

        $this->assertSame('TRIMESTRAL', $setting->tipo_evaluacion);
        $this->assertSame('AUTOEVALUACION', $setting->etapa_vigente);
        $this->assertTrue($setting->tipo_evaluacion === 'TRIMESTRAL');
        $this->assertTrue($setting->etapa_vigente === 'AUTOEVALUACION');
    }

    public function test_expose_string_values_for_cutoff_bread_fields(): void
    {
        $cutoff = Cutoff::unguarded(fn () => Cutoff::create([
            'clave' => CutoffClave::CorteII,
            'tipo_corte' => CutoffTipo::Semestral,
            'meses' => json_encode([1, 6]),
            'orden' => 2,
            'habilitado' => true,
        ]));

        $cutoff = $cutoff->fresh();

        $this->assertInstanceOf(CutoffClave::class, $cutoff->clave);

        VoyagerBackedEnumBreadForm::exposeStringValues(
            $cutoff,
            VoyagerBackedEnumBreadForm::CUTOFF_BREAD_ENUM_FIELDS
        );

        $this->assertSame('CORTE II', $cutoff->clave);
        $this->assertSame('SEMESTRAL', $cutoff->tipo_corte);
    }

    public function test_expose_string_values_for_cen_center_bread_fields(): void
    {
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $center = $this->createMinimalCenCenter([
            'codigo' => 'CEN-ENUM',
            'nombre_oficial' => 'Centro Enum Test',
            'nombre_corto' => 'CEnum',
            'nivel_atencion' => CentroNivelAtencion::Secundario->value,
        ])->fresh();

        $this->assertInstanceOf(CenCenter::class, $center);
        $this->assertInstanceOf(CentroNivelAtencion::class, $center->nivel_atencion);

        VoyagerBackedEnumBreadForm::exposeStringValues(
            $center,
            VoyagerBackedEnumBreadForm::CEN_CENTER_BREAD_ENUM_FIELDS
        );

        $expectedNivel = CentroNivelAtencion::Secundario->value;

        $this->assertSame($expectedNivel, $center->nivel_atencion);
        $this->assertTrue($center->nivel_atencion === $expectedNivel);
    }

    public function test_display_label_from_enum_or_string(): void
    {
        $this->assertSame('Cerrado', VoyagerBackedEnumBreadForm::displayLabel(CentroEstadoFuncionamiento::Cerrado));
        $this->assertSame('Cerrado', VoyagerBackedEnumBreadForm::displayLabel('Cerrado'));
        $this->assertSame('—', VoyagerBackedEnumBreadForm::displayLabel(null));
    }
}
