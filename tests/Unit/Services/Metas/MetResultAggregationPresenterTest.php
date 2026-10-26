<?php

namespace Tests\Unit\Services\Metas;

use App\Enums\Metas\DataFieldComponente;
use App\Models\MetDataField;
use App\Models\MetFormula;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetResult;
use App\Services\Metas\MetIndicatorCalculationDataScopeService;
use App\Services\Metas\MetResultAggregationPresenter;
use App\Services\MetDataAggregatorService;
use Tests\TestCase;

class MetResultAggregationPresenterTest extends TestCase
{
    private MetResultAggregationPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->presenter = app(MetResultAggregationPresenter::class);
    }

    /** @test */
    public function aggregate_period_results_usa_resultado_almacenado_cuando_no_hay_componentes_primarios(): void
    {
        $effectiveFields = collect([
            $this->makeField(DataFieldComponente::Numerador, 'semestral', 'sumatoria'),
            $this->makeField(DataFieldComponente::Denominador, 'semestral', 'sumatoria'),
        ]);

        $group = collect([
            $this->makeResult('SEM-1', null, null, 83.61),
            $this->makeResult('SEM-2', null, null, 74.33),
        ]);

        $aggregated = $this->presenter->aggregatePeriodResultsToAnnualSummary(
            $group,
            $effectiveFields,
            2,
            'redondear',
            ['SEM-1' => 1, 'SEM-2' => 2],
        );

        $this->assertSame(0.0, $aggregated['numerador']);
        $this->assertSame(0.0, $aggregated['denominador']);
        $this->assertSame(74.33, $aggregated['resultado']);
        $this->assertSame('SEM-2', $aggregated['ultimo_periodo']);
    }

    /** @test */
    public function aggregate_period_results_no_suma_denominador_linea_base(): void
    {
        $effectiveFields = collect([
            $this->makeField(DataFieldComponente::Numerador, 'semestral', 'sumatoria'),
            $this->makeField(DataFieldComponente::Denominador, 'linea_base', null),
        ]);

        $group = collect([
            $this->makeResult('SEM-1', 123, 250, 49.2),
            $this->makeResult('SEM-2', 123, 250, 49.2),
        ]);

        $aggregated = $this->presenter->aggregatePeriodResultsToAnnualSummary(
            $group,
            $effectiveFields,
            2,
            'redondear',
            ['SEM-1' => 1, 'SEM-2' => 2],
        );

        $this->assertSame(246.0, $aggregated['numerador']);
        $this->assertSame(250.0, $aggregated['denominador']);
        $this->assertSame(98.4, $aggregated['resultado']);
    }

    /** @test */
    public function aggregate_period_results_devuelve_error_divisor_cero_sin_resultado_almacenado(): void
    {
        $effectiveFields = collect([
            $this->makeField(DataFieldComponente::Numerador, 'semestral', 'sumatoria'),
            $this->makeField(DataFieldComponente::Denominador, 'semestral', 'sumatoria'),
        ]);

        $group = collect([
            $this->makeResult('SEM-1', 10.0, 0.0, null),
            $this->makeResult('SEM-2', 20.0, 0.0, null),
        ]);

        $aggregated = $this->presenter->aggregatePeriodResultsToAnnualSummary(
            $group,
            $effectiveFields,
            2,
            'redondear',
            ['SEM-1' => 1, 'SEM-2' => 2],
            $this->makeRatioIndicator(),
        );

        $this->assertSame(30.0, $aggregated['numerador']);
        $this->assertSame(0.0, $aggregated['denominador']);
        $this->assertNull($aggregated['resultado']);
        $this->assertSame(MetResult::MESSAGE_DIVISOR_CERO, $aggregated['error_message']);
        $this->assertSame('SEM-2', $aggregated['ultimo_periodo']);
    }

    /** @test */
    public function aggregate_period_results_anual_cero_sobre_cero_devuelve_cero(): void
    {
        $effectiveFields = collect([
            $this->makeField(DataFieldComponente::Numerador, 'mensual', 'sumatoria'),
            $this->makeField(DataFieldComponente::Denominador, 'mensual', 'sumatoria'),
        ]);

        $indicator = $this->makeRatioIndicator();

        $group = collect([
            $this->makeResult('MES-11', 0.0, 0.0, 0.0),
            $this->makeResult('MES-12', 0.0, 0.0, 0.0),
        ]);

        $aggregated = $this->presenter->aggregatePeriodResultsToAnnualSummary(
            $group,
            $effectiveFields,
            2,
            'redondear',
            ['MES-11' => 11, 'MES-12' => 12],
            $indicator,
        );

        $this->assertSame(0.0, $aggregated['numerador']);
        $this->assertSame(0.0, $aggregated['denominador']);
        $this->assertSame(0.0, $aggregated['resultado']);
        $this->assertNull($aggregated['error_message']);
    }

    /** @test */
    public function aggregate_period_results_no_division_by_zero_when_numerador_es_cero(): void
    {
        $effectiveFields = collect([
            $this->makeField(DataFieldComponente::Numerador, 'mensual', 'sumatoria'),
            $this->makeField(DataFieldComponente::Denominador, 'mensual', 'sumatoria'),
        ]);

        $group = collect([
            $this->makeResult('MES-11', 0.0, 25.0, 0.0),
            $this->makeResult('MES-12', 10.0, 20.0, 50.0),
        ]);

        $aggregated = $this->presenter->aggregatePeriodResultsToAnnualSummary(
            $group,
            $effectiveFields,
            2,
            'redondear',
            ['MES-11' => 11, 'MES-12' => 12],
            $this->makeRatioIndicator(),
        );

        $this->assertSame(10.0, $aggregated['numerador']);
        $this->assertSame(45.0, $aggregated['denominador']);
        $this->assertSame(22.22, $aggregated['resultado']);
        $this->assertNull($aggregated['error_message']);
    }

    /** @test */
    public function append_cumulative_period_data_no_division_by_zero_when_numerador_es_cero(): void
    {
        $effectiveFields = collect([
            $this->makeField(DataFieldComponente::Numerador, 'mensual', 'sumatoria'),
            $this->makeField(DataFieldComponente::Denominador, 'mensual', 'sumatoria'),
        ]);

        $periodos = [
            [
                'periodo' => 'MES-11',
                'numerador' => 0.0,
                'denominador' => 25.0,
                'resultado' => 0.0,
            ],
            [
                'periodo' => 'MES-12',
                'numerador' => 10.0,
                'denominador' => 20.0,
                'resultado' => 50.0,
            ],
        ];

        $updated = $this->presenter->appendCumulativePeriodData(
            $periodos,
            $effectiveFields,
            $this->makeRatioIndicator(),
        );

        $this->assertSame(10.0, $updated[1]['numerador_acumulado']);
        $this->assertSame(45.0, $updated[1]['denominador_acumulado']);
        $this->assertSame(22.22, $updated[1]['resultado_acumulado']);
    }

    /** @test */
    public function aggregate_period_results_aplica_formula_sobre_sumas_con_truncamiento_mensual(): void
    {
        $effectiveFields = collect([
            $this->makeField(DataFieldComponente::Numerador, 'mensual', 'sumatoria'),
            $this->makeField(DataFieldComponente::Denominador, 'linea_base', null),
        ]);

        $indicator = $this->makeRatioIndicator('truncar');

        $group = collect([
            $this->makeResult('MES-01', 0.0, 96.0, 0.0),
            $this->makeResult('MES-02', 0.0, 96.0, 0.0),
            $this->makeResult('MES-03', 2.0, 96.0, 2.08),
            $this->makeResult('MES-04', 1.0, 96.0, 1.04),
            $this->makeResult('MES-05', 11.0, 96.0, 11.45),
            $this->makeResult('MES-06', 0.0, 96.0, 0.0),
            $this->makeResult('MES-07', 1.0, 96.0, 1.04),
            $this->makeResult('MES-08', 1.0, 96.0, 1.04),
        ]);

        $aggregated = $this->presenter->aggregatePeriodResultsToAnnualSummary(
            $group,
            $effectiveFields,
            2,
            'truncar',
            ['MES-01' => 1, 'MES-02' => 2, 'MES-03' => 3, 'MES-04' => 4, 'MES-05' => 5, 'MES-06' => 6, 'MES-07' => 7, 'MES-08' => 8],
            $indicator,
        );

        $this->assertSame(16.0, $aggregated['numerador']);
        $this->assertSame(96.0, $aggregated['denominador']);
        $this->assertSame(16.66, $aggregated['resultado']);
        $this->assertNull($aggregated['error_message']);
    }

    /** @test */
    public function aggregate_period_results_ultimo_dato_aplica_formula_sobre_ultimo_periodo_sin_factor_inferido(): void
    {
        $effectiveFields = collect([
            $this->makeField(DataFieldComponente::Numerador, 'trimestral', 'ultimo_dato'),
            $this->makeField(DataFieldComponente::Denominador, 'linea_base', null),
        ]);

        $indicator = $this->makeRatioIndicator('truncar');

        $group = collect([
            $this->makeResult('TRIM-1', null, 129.0, null),
            $this->makeResult('TRIM-2', 92.0, 129.0, 71.31),
            $this->makeResult('TRIM-3', 107.0, 129.0, 82.94),
            $this->makeResult('TRIM-4', 115.0, 129.0, 89.14),
        ]);

        $aggregated = $this->presenter->aggregatePeriodResultsToAnnualSummary(
            $group,
            $effectiveFields,
            2,
            'truncar',
            ['TRIM-1' => 1, 'TRIM-2' => 2, 'TRIM-3' => 3, 'TRIM-4' => 4],
            $indicator,
        );

        $this->assertSame(115.0, $aggregated['numerador']);
        $this->assertSame(129.0, $aggregated['denominador']);
        $this->assertSame(89.14, $aggregated['resultado']);
        $this->assertSame('TRIM-4', $aggregated['ultimo_periodo']);
    }

    /** @test */
    public function aggregate_period_results_re_evalua_formula_compleja_sin_colapsar_denominadores(): void
    {
        $this->mock(MetDataAggregatorService::class, function ($mock): void {
            $mock->shouldReceive('aggregateVariables')
                ->twice()
                ->andReturn(
                    ['A' => 10.0, 'B' => 100.0, 'C' => 5.0],
                    ['A' => 20.0, 'B' => 200.0, 'C' => 10.0],
                );
            $mock->shouldReceive('resolvePrimaryComponenteValues')
                ->once()
                ->withArgs(function ($indicator, array $variables): bool {
                    return $variables['A'] === 30.0
                        && $variables['B'] === 300.0
                        && $variables['C'] === 15.0;
                })
                ->andReturn(['numerador' => 30.0, 'denominador' => null]);
        });

        $presenter = app(MetResultAggregationPresenter::class);

        $effectiveFields = collect([
            $this->makeComplexField('A', DataFieldComponente::Numerador, 'mensual', 'sumatoria'),
            $this->makeComplexField('B', DataFieldComponente::Denominador, 'mensual', 'sumatoria'),
            $this->makeComplexField('C', DataFieldComponente::Denominador, 'mensual', 'sumatoria'),
        ]);

        $indicator = $this->makeComplexFormulaIndicator();

        $group = collect([
            $this->makeResult('MES-01', 10.0, null, 5.26),
            $this->makeResult('MES-02', 20.0, null, 10.53),
        ]);

        $aggregated = $presenter->aggregatePeriodResultsToAnnualSummary(
            $group,
            $effectiveFields,
            2,
            'redondear',
            ['MES-01' => 1, 'MES-02' => 2],
            $indicator,
        );

        $this->assertNull($aggregated['error_message']);
        $this->assertSame(10.53, $aggregated['resultado']);
        $this->assertSame('MES-02', $aggregated['ultimo_periodo']);
    }

    /** @test */
    public function aggregate_period_results_re_evalua_formula_compleja_en_el_universo_agrupa_ss(): void
    {
        $indicator = $this->makeComplexFormulaIndicator();
        $indicator->id = 1;

        $configuration = new MetIndicatorConfiguration([
            'met_indicator_id' => $indicator->id,
            'cen_center_id' => 100,
            'year_id' => 2025,
            'cen_hie_dependency_id' => 47,
            'agrupa_ss' => true,
        ]);

        $this->mock(MetIndicatorCalculationDataScopeService::class, function ($mock) use ($indicator, $configuration): void {
            $mock->shouldReceive('additionalDataCenterIds')
                ->once()
                ->with($indicator, 100, $configuration, 2025, 47, null)
                ->andReturn([200]);
        });

        $this->mock(MetDataAggregatorService::class, function ($mock): void {
            $mock->shouldReceive('aggregateVariables')
                ->twice()
                ->withArgs(fn ($indicator, $centerId, $yearId, $periodo, $additionalCenterIds) => $centerId === 100
                    && $yearId === 2025
                    && $additionalCenterIds === [200])
                ->andReturn(
                    ['A' => 10.0, 'B' => 100.0, 'C' => 5.0],
                    ['A' => 20.0, 'B' => 200.0, 'C' => 10.0],
                );
            $mock->shouldReceive('resolvePrimaryComponenteValues')
                ->once()
                ->andReturn(['numerador' => 30.0, 'denominador' => null]);
        });

        $presenter = app(MetResultAggregationPresenter::class);
        $group = collect([
            $this->makeScopedResult('MES-01', 5.26),
            $this->makeScopedResult('MES-02', 10.53),
        ]);

        $aggregated = $presenter->aggregatePeriodResultsToAnnualSummary(
            $group,
            collect([
                $this->makeComplexField('A', DataFieldComponente::Numerador, 'mensual', 'sumatoria'),
                $this->makeComplexField('B', DataFieldComponente::Denominador, 'mensual', 'sumatoria'),
                $this->makeComplexField('C', DataFieldComponente::Denominador, 'mensual', 'sumatoria'),
            ]),
            2,
            'redondear',
            ['MES-01' => 1, 'MES-02' => 2],
            $indicator,
            $configuration,
        );

        $this->assertSame(10.53, $aggregated['resultado']);
        $this->assertNull($aggregated['error_message']);
    }

    /** @test */
    public function aggregate_period_results_re_evalua_formula_multi_numerador_y_multi_denominador(): void
    {
        $this->mock(MetDataAggregatorService::class, function ($mock): void {
            $mock->shouldReceive('aggregateVariables')
                ->twice()
                ->andReturn(
                    ['A' => 5.0, 'B' => 5.0, 'C' => 5.0, 'D' => 5.0, 'E' => 5.0],
                    ['A' => 10.0, 'B' => 10.0, 'C' => 10.0, 'D' => 5.0, 'E' => 5.0],
                );
            $mock->shouldReceive('resolvePrimaryComponenteValues')
                ->once()
                ->withArgs(function ($indicator, array $variables): bool {
                    return $variables['A'] === 15.0
                        && $variables['B'] === 15.0
                        && $variables['C'] === 15.0
                        && $variables['D'] === 10.0
                        && $variables['E'] === 10.0;
                })
                ->andReturn(['numerador' => 45.0, 'denominador' => 65.0]);
        });

        $presenter = app(MetResultAggregationPresenter::class);

        $effectiveFields = collect([
            $this->makeComplexField('A', DataFieldComponente::Numerador, 'mensual', 'sumatoria'),
            $this->makeComplexField('B', DataFieldComponente::Numerador, 'mensual', 'sumatoria'),
            $this->makeComplexField('C', DataFieldComponente::Numerador, 'mensual', 'sumatoria'),
            $this->makeComplexField('D', DataFieldComponente::Denominador, 'mensual', 'sumatoria'),
            $this->makeComplexField('E', DataFieldComponente::Denominador, 'mensual', 'sumatoria'),
        ]);

        $indicator = $this->makeMultiNumDenFormulaIndicator();

        $group = collect([
            $this->makeResult('MES-01', null, null, 50.0),
            $this->makeResult('MES-02', null, null, 66.67),
        ]);

        $aggregated = $presenter->aggregatePeriodResultsToAnnualSummary(
            $group,
            $effectiveFields,
            2,
            'redondear',
            ['MES-01' => 1, 'MES-02' => 2],
            $indicator,
        );

        $this->assertNull($aggregated['error_message']);
        $this->assertSame(69.23, $aggregated['resultado']);
        $this->assertSame(45.0, $aggregated['numerador']);
        $this->assertSame(65.0, $aggregated['denominador']);
        $this->assertSame('MES-02', $aggregated['ultimo_periodo']);
    }

    /** @test */
    public function append_cumulative_period_data_no_suma_denominador_linea_base(): void
    {
        $effectiveFields = collect([
            $this->makeField(DataFieldComponente::Numerador, 'semestral', 'sumatoria'),
            $this->makeField(DataFieldComponente::Denominador, 'linea_base', null),
        ]);

        $periodos = [
            [
                'periodo' => 'SEM-1',
                'numerador' => 123.0,
                'denominador' => 250.0,
                'resultado' => 49.2,
            ],
            [
                'periodo' => 'SEM-2',
                'numerador' => 123.0,
                'denominador' => 250.0,
                'resultado' => 49.2,
            ],
        ];

        $updated = $this->presenter->appendCumulativePeriodData(
            $periodos,
            $effectiveFields,
            $this->makeRatioIndicator(),
        );

        $this->assertSame(246.0, $updated[1]['numerador_acumulado']);
        $this->assertSame(250.0, $updated[1]['denominador_acumulado']);
        $this->assertSame(98.4, $updated[1]['resultado_acumulado']);
    }

    /** @test */
    public function append_complex_cumulative_period_data_reevalua_formula_sobre_variables_acumuladas(): void
    {
        $this->mock(MetDataAggregatorService::class, function ($mock): void {
            $mock->shouldReceive('aggregateVariables')
                ->twice()
                ->andReturn(
                    ['A' => 1264.0, 'B' => 1517.0],
                    ['A' => 1241.0, 'B' => 1296.0],
                );
            $mock->shouldReceive('resolvePrimaryComponenteValues')
                ->twice()
                ->andReturn(
                    ['numerador' => null, 'denominador' => null],
                    ['numerador' => null, 'denominador' => null],
                );
        });

        $presenter = app(MetResultAggregationPresenter::class);
        $indicator = $this->makeDualNumeradorFormulaIndicator();
        $effectiveFields = $indicator->dataFields;

        $periodos = [
            [
                'periodo' => 'MES-01',
                'numerador' => null,
                'denominador' => null,
                'resultado' => -16.67,
            ],
            [
                'periodo' => 'MES-02',
                'numerador' => null,
                'denominador' => null,
                'resultado' => -4.24,
            ],
        ];

        $updated = $presenter->appendComplexCumulativePeriodData(
            $periodos,
            $effectiveFields,
            $indicator,
            390,
            4,
            47,
        );

        // MES-01: (1264-1517)/1517*100
        $this->assertSame(-16.68, $updated[0]['resultado_acumulado']);
        // MES-02 YTD: A=2505, B=2813 → (2505-2813)/2813*100
        $this->assertSame(-10.95, $updated[1]['resultado_acumulado']);
    }

    private function makeField(DataFieldComponente $componente, string $inputType, ?string $aggregationType): MetDataField
    {
        $field = new MetDataField([
            'componente' => $componente,
            'input_type' => $inputType,
            'aggregation_type' => $aggregationType,
        ]);

        $field->syncOriginal();

        return $field;
    }

    private function makeResult(string $periodo, ?float $numerador, ?float $denominador, ?float $resultado, ?string $errorMessage = null): MetResult
    {
        return new MetResult([
            'periodo' => $periodo,
            'numerador' => $numerador,
            'denominador' => $denominador,
            'resultado' => $resultado,
            'error_message' => $errorMessage,
        ]);
    }

    private function makeScopedResult(string $periodo, float $resultado): MetResult
    {
        return new MetResult([
            'cen_center_id' => 100,
            'year_id' => 2025,
            'cen_hie_dependency_id' => 47,
            'periodo' => $periodo,
            'resultado' => $resultado,
        ]);
    }

    private function makeRatioIndicator(string $decimalesModo = 'redondear'): MetIndicator
    {
        $indicator = new MetIndicator([
            'decimales' => 2,
            'decimales_modo' => $decimalesModo,
        ]);

        $indicator->setRelation('dataFields', collect([
            new MetDataField([
                'variable_key' => 'A',
                'componente' => DataFieldComponente::Numerador,
            ]),
            new MetDataField([
                'variable_key' => 'B',
                'componente' => DataFieldComponente::Denominador,
            ]),
        ]));

        $indicator->setRelation('formula', new MetFormula([
            'expression' => [
                ['type' => 'var', 'key' => 'A'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'var', 'key' => 'B'],
                ['type' => 'op', 'value' => '*'],
                ['type' => 'lit', 'value' => '100'],
            ],
        ]));

        return $indicator;
    }

    private function makeComplexField(
        string $variableKey,
        DataFieldComponente $componente,
        string $inputType,
        ?string $aggregationType,
    ): MetDataField {
        $field = new MetDataField([
            'variable_key' => $variableKey,
            'componente' => $componente,
            'input_type' => $inputType,
            'aggregation_type' => $aggregationType,
        ]);
        $field->syncOriginal();

        return $field;
    }

    private function makeComplexFormulaIndicator(): MetIndicator
    {
        $indicator = new MetIndicator([
            'decimales' => 2,
            'decimales_modo' => 'redondear',
        ]);

        $indicator->setRelation('dataFields', collect([
            $this->makeComplexField('A', DataFieldComponente::Numerador, 'mensual', 'sumatoria'),
            $this->makeComplexField('B', DataFieldComponente::Denominador, 'mensual', 'sumatoria'),
            $this->makeComplexField('C', DataFieldComponente::Denominador, 'mensual', 'sumatoria'),
        ]));

        $indicator->setRelation('formula', new MetFormula([
            'expression' => [
                ['type' => 'var', 'key' => 'A'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'paren', 'value' => '('],
                ['type' => 'var', 'key' => 'B'],
                ['type' => 'op', 'value' => '-'],
                ['type' => 'var', 'key' => 'C'],
                ['type' => 'paren', 'value' => ')'],
                ['type' => 'op', 'value' => '*'],
                ['type' => 'lit', 'value' => '100'],
            ],
        ]));

        return $indicator;
    }

    private function makeMultiNumDenFormulaIndicator(): MetIndicator
    {
        $indicator = new MetIndicator([
            'decimales' => 2,
            'decimales_modo' => 'redondear',
        ]);

        $indicator->setRelation('dataFields', collect([
            $this->makeComplexField('A', DataFieldComponente::Numerador, 'mensual', 'sumatoria'),
            $this->makeComplexField('B', DataFieldComponente::Numerador, 'mensual', 'sumatoria'),
            $this->makeComplexField('C', DataFieldComponente::Numerador, 'mensual', 'sumatoria'),
            $this->makeComplexField('D', DataFieldComponente::Denominador, 'mensual', 'sumatoria'),
            $this->makeComplexField('E', DataFieldComponente::Denominador, 'mensual', 'sumatoria'),
        ]));

        $indicator->setRelation('formula', new MetFormula([
            'expression' => [
                ['type' => 'paren', 'value' => '('],
                ['type' => 'var', 'key' => 'A'],
                ['type' => 'op', 'value' => '+'],
                ['type' => 'var', 'key' => 'B'],
                ['type' => 'op', 'value' => '+'],
                ['type' => 'var', 'key' => 'C'],
                ['type' => 'paren', 'value' => ')'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'paren', 'value' => '('],
                ['type' => 'var', 'key' => 'A'],
                ['type' => 'op', 'value' => '+'],
                ['type' => 'var', 'key' => 'B'],
                ['type' => 'op', 'value' => '+'],
                ['type' => 'var', 'key' => 'C'],
                ['type' => 'op', 'value' => '+'],
                ['type' => 'var', 'key' => 'D'],
                ['type' => 'op', 'value' => '+'],
                ['type' => 'var', 'key' => 'E'],
                ['type' => 'paren', 'value' => ')'],
                ['type' => 'op', 'value' => '*'],
                ['type' => 'lit', 'value' => '100'],
            ],
        ]));

        return $indicator;
    }

    private function makeDualNumeradorFormulaIndicator(): MetIndicator
    {
        $indicator = new MetIndicator([
            'decimales' => 2,
            'decimales_modo' => 'redondear',
        ]);

        $indicator->setRelation('dataFields', collect([
            $this->makeComplexField('A', DataFieldComponente::Numerador, 'mensual', 'sumatoria'),
            $this->makeComplexField('B', DataFieldComponente::Numerador, 'mensual', 'sumatoria'),
        ]));

        $indicator->setRelation('formula', new MetFormula([
            'expression' => [
                ['type' => 'paren', 'value' => '('],
                ['type' => 'var', 'key' => 'A'],
                ['type' => 'op', 'value' => '-'],
                ['type' => 'var', 'key' => 'B'],
                ['type' => 'paren', 'value' => ')'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'var', 'key' => 'B'],
                ['type' => 'op', 'value' => '*'],
                ['type' => 'lit', 'value' => '100'],
            ],
        ]));

        return $indicator;
    }
}
