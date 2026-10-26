<?php

namespace Tests\Unit\Models;

use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\MetDataField;
use App\Models\MetFieldSsConfig;
use App\Models\MetIndicator;
use App\Models\MetMonthlyData;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MetMonthlyDataTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private CenCenter $center;

    private MetDataField $field;

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

        $indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $indicatorType->id,
            'clave' => 'MET-01',
            'nombre' => 'Indicador test',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'year_id' => $this->year->id,
            'orden' => 1,
            'estado' => true,
        ]));

        $this->field = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Denominador',
            'variable_key' => 'B',
            'componente' => 'denominador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 0,
        ]));

        $this->center = $this->createMinimalCenCenter([
            'codigo' => 'CEN-001',
            'nombre_oficial' => 'Hospital Test',
            'nombre_corto' => 'HospTest',
        ]);
    }

    /** @test */
    public function permite_linea_base_con_override_ss_sin_relacion_data_field(): void
    {
        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
            'met_data_field_id' => $this->field->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'linea_base',
            'aggregation_type' => null,
        ]));

        $record = MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->field->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => null,
            'valor' => 75,
        ]));

        $this->assertDatabaseHas('met_monthly_data', [
            'id' => $record->id,
            'mes' => null,
            'valor' => 75,
        ]);
    }

    /** @test */
    public function rechaza_mes_invalido_segun_tipo_efectivo_trimestral(): void
    {
        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::updateOrCreate(
            [
                'met_data_field_id' => $this->field->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
            ],
            [
                'input_type' => 'trimestral',
                'aggregation_type' => 'sumatoria',
            ]
        ));

        $this->expectException(ValidationException::class);

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->field->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 1,
            'valor' => 10,
        ]));
    }
}
