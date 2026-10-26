<?php

namespace Tests\Unit\Services\Metas;

use App\Enums\Periodicidad;
use App\Models\CenHieDependency;
use App\Models\MetDataField;
use App\Models\MetFieldSsConfig;
use App\Models\MetIndicator;
use App\Models\Year;
use App\Services\Metas\MetIndicatorPeriodicityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetIndicatorPeriodicityResolverTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private MetIndicator $indicator;

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

        $this->indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $indicatorType->id,
            'clave' => 'MET-PER-01',
            'nombre' => 'Indicador periodicidad carga',
            'decimales' => 2,
            'year_id' => $this->year->id,
            'estado' => true,
        ]));
    }

    /** @test */
    public function resolve_prefers_mensual_over_semestral_among_numeradores(): void
    {
        $this->createNumeradorField('A', 'mensual', 0);
        $this->createNumeradorField('B', 'semestral', 1);

        $resolved = app(MetIndicatorPeriodicityResolver::class)
            ->resolveForCarga($this->indicator, 1, $this->year->id);

        $this->assertSame(Periodicidad::Mensual, $resolved);
    }

    /** @test */
    public function resolve_uses_semestral_when_only_semestral_numerador(): void
    {
        $this->createNumeradorField('A', 'semestral', 0);

        $resolved = app(MetIndicatorPeriodicityResolver::class)
            ->resolveForCarga($this->indicator, 1, $this->year->id);

        $this->assertSame(Periodicidad::Semestral, $resolved);
    }

    /** @test */
    public function resolve_falls_back_to_default_without_numeradores(): void
    {
        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Denominador',
            'variable_key' => 'B',
            'componente' => 'denominador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 0,
        ]));

        $resolved = app(MetIndicatorPeriodicityResolver::class)
            ->resolveForCarga($this->indicator, 1, $this->year->id);

        $this->assertSame(Periodicidad::Anual, $resolved);
    }

    /** @test */
    public function resolve_honors_ss_override_input_type(): void
    {
        $field = $this->createNumeradorField('A', 'mensual', 0);

        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
            'met_data_field_id' => $field->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'semestral',
            'aggregation_type' => 'sumatoria',
        ]));

        $resolved = app(MetIndicatorPeriodicityResolver::class)
            ->resolveForCarga($this->indicator, 1, $this->year->id);

        $this->assertSame(Periodicidad::Semestral, $resolved);
    }

    private function createNumeradorField(string $variableKey, string $inputType, int $orden): MetDataField
    {
        return MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => "Numerador {$variableKey}",
            'variable_key' => $variableKey,
            'componente' => 'numerador',
            'input_type' => $inputType,
            'aggregation_type' => $inputType === 'linea_base' ? null : 'sumatoria',
            'orden' => $orden,
        ]));
    }
}
