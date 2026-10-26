<?php

namespace Tests\Unit\Services\Metas;

use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorTypeSetting;
use App\Models\MetDataField;
use App\Models\MetFieldSsConfig;
use App\Models\MetIndicator;
use App\Models\MetMonthlyData;
use App\Models\Year;
use App\Services\Metas\MetIndicatorIncompletePeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MetIndicatorIncompletePeriodServiceTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private MetIndicator $indicator;

    private MetDataField $fieldA;

    private MetDataField $fieldB;

    private CenCenter $center;

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

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $indicatorType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'habilitado' => true,
            'etapa_vigente' => 'AUTOEVALUACION',
        ]));

        $this->indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $indicatorType->id,
            'year_id' => $this->year->id,
            'clave' => 'MET-TEST-01',
            'nombre' => 'Indicador test',
            'estado' => true,
            'orden' => 1,
        ]));

        $this->fieldA = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Numerador',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 1,
            'variable_key' => 'A',
        ]));

        $this->fieldB = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Denominador',
            'componente' => 'denominador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 2,
            'variable_key' => 'B',
        ]));

        foreach ([$this->fieldA, $this->fieldB] as $field) {
            MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
                'met_data_field_id' => $field->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'input_type' => 'mensual',
                'aggregation_type' => 'sumatoria',
            ]));
        }

        $this->center = $this->createMinimalCenCenter([
            'codigo' => 'CEN-INC-01',
            'nombre_corto' => 'Centro incompleto',
        ]);
    }

    /** @test */
    public function detecta_periodo_parcial_en_bd(): void
    {
        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 2,
            'valor' => 4,
        ]));

        $service = app(MetIndicatorIncompletePeriodService::class);

        $this->assertTrue($service->hasIncompletePeriodsForIndicator(
            (int) $this->indicator->id,
            1,
            $this->year->id,
        ));

        $byCenter = $service->incompletePeriodsByCenterId(
            (int) $this->indicator->id,
            1,
            $this->year->id,
            [(int) $this->center->id],
        );

        $this->assertTrue($byCenter->has((int) $this->center->id));
        $this->assertSame(1, $byCenter[(int) $this->center->id]['count']);
    }

    /** @test */
    public function incomplete_month_numbers_for_center_returns_mes_afectados(): void
    {
        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 4,
            'valor' => 10,
        ]));

        $service = app(MetIndicatorIncompletePeriodService::class);

        $months = $service->incompleteMonthNumbersForCenter(
            (int) $this->indicator->id,
            (int) $this->center->id,
            1,
            $this->year->id,
        );

        $this->assertSame([4], $months);
    }

    /** @test */
    public function periodo_completo_con_ceros_no_genera_alerta(): void
    {
        foreach ([$this->fieldA, $this->fieldB] as $field) {
            MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
                'met_data_field_id' => $field->id,
                'cen_center_id' => $this->center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'mes' => 3,
                'valor' => 0,
            ]));
        }

        $service = app(MetIndicatorIncompletePeriodService::class);

        $this->assertFalse($service->hasIncompletePeriodsForIndicator(
            (int) $this->indicator->id,
            1,
            $this->year->id,
        ));
    }

    /** @test */
    public function indicator_ids_batch_matches_per_indicator_detection(): void
    {
        $indicatorComplete = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicator->indicator_type_id,
            'year_id' => $this->year->id,
            'clave' => 'MET-TEST-02',
            'nombre' => 'Indicador completo',
            'estado' => true,
            'orden' => 2,
        ]));

        $completeFieldA = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicatorComplete->id,
            'label' => 'Numerador',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 1,
            'variable_key' => 'A',
        ]));

        $completeFieldB = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicatorComplete->id,
            'label' => 'Denominador',
            'componente' => 'denominador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 2,
            'variable_key' => 'B',
        ]));

        foreach ([$completeFieldA, $completeFieldB] as $field) {
            MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
                'met_data_field_id' => $field->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'input_type' => 'mensual',
                'aggregation_type' => 'sumatoria',
            ]));
        }

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 5,
            'valor' => 2,
        ]));

        foreach ([$completeFieldA, $completeFieldB] as $field) {
            MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
                'met_data_field_id' => $field->id,
                'cen_center_id' => $this->center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'mes' => 5,
                'valor' => 1,
            ]));
        }

        $service = app(MetIndicatorIncompletePeriodService::class);

        $ids = [
            (int) $this->indicator->id,
            (int) $indicatorComplete->id,
        ];

        $batch = $service->indicatorIdsWithIncompletePeriods($ids, 1, $this->year->id);

        $this->assertSame([(int) $this->indicator->id], $batch->all());
        $this->assertTrue($service->hasIncompletePeriodsForIndicator((int) $this->indicator->id, 1, $this->year->id));
        $this->assertFalse($service->hasIncompletePeriodsForIndicator((int) $indicatorComplete->id, 1, $this->year->id));
        $this->assertTrue(
            $service->incompletePeriodsByCenterId((int) $this->indicator->id, 1, $this->year->id)->isNotEmpty(),
        );
        $this->assertTrue(
            $service->incompletePeriodsByCenterId((int) $indicatorComplete->id, 1, $this->year->id)->isEmpty(),
        );
    }

    /** @test */
    public function indicator_ids_empty_returns_empty_collection(): void
    {
        $service = app(MetIndicatorIncompletePeriodService::class);

        $this->assertSame([], $service->indicatorIdsWithIncompletePeriods([], 1, $this->year->id)->all());
    }

    /** @test */
    public function indicator_ids_batch_uses_few_met_field_ss_config_queries(): void
    {
        $extraIndicators = [];

        for ($i = 0; $i < 4; $i++) {
            $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
                'indicator_type_id' => $this->indicator->indicator_type_id,
                'year_id' => $this->year->id,
                'clave' => 'MET-PERF-'.$i,
                'nombre' => 'Indicador perf '.$i,
                'estado' => true,
                'orden' => 10 + $i,
            ]));

            $fields = [];
            foreach (['A', 'B'] as $orden => $key) {
                $field = MetDataField::unguarded(fn () => MetDataField::create([
                    'met_indicator_id' => $indicator->id,
                    'label' => 'Campo '.$key,
                    'componente' => $key === 'A' ? 'numerador' : 'denominador',
                    'input_type' => 'mensual',
                    'aggregation_type' => 'sumatoria',
                    'orden' => $orden + 1,
                    'variable_key' => $key,
                ]));
                MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
                    'met_data_field_id' => $field->id,
                    'cen_hie_dependency_id' => 1,
                    'year_id' => $this->year->id,
                    'input_type' => 'mensual',
                    'aggregation_type' => 'sumatoria',
                ]));
                $fields[] = $field;
            }

            MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
                'met_data_field_id' => $fields[0]->id,
                'cen_center_id' => $this->center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'mes' => 6,
                'valor' => 3,
            ]));

            $extraIndicators[] = (int) $indicator->id;
        }

        $ids = array_merge([(int) $this->indicator->id], $extraIndicators);

        DB::flushQueryLog();
        DB::enableQueryLog();

        app(MetIndicatorIncompletePeriodService::class)
            ->indicatorIdsWithIncompletePeriods($ids, 1, $this->year->id);

        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $ssConfigQueries = collect($log)->filter(
            fn (array $entry): bool => str_contains(strtolower($entry['query']), 'met_field_ss_configs'),
        )->count();

        // SDD-172: batch preload (O(1)), no O(campos×meses). 5 indicadores × 2 campos → sin fix serían cientos.
        $this->assertLessThanOrEqual(3, $ssConfigQueries, 'met_field_ss_configs queries: '.$ssConfigQueries);
    }

    /** @test */
    public function batch_incomplete_periods_by_indicators_matches_per_indicator_detail(): void
    {
        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 3,
            'valor' => 10,
        ]));

        $service = app(MetIndicatorIncompletePeriodService::class);
        $indicator = $this->indicator->fresh(['dataFields', 'indicatorType']);

        $single = $service->incompletePeriodsByCenterId(
            (int) $indicator->id,
            1,
            (int) $this->year->id,
            [(int) $this->center->id],
        );

        $batch = $service->incompletePeriodsByIndicators(
            MetIndicator::query()->whereKey([$indicator->id])->with(['dataFields', 'indicatorType'])->get(),
            1,
            (int) $this->year->id,
            [(int) $this->center->id],
        )->get((int) $indicator->id);

        $this->assertNotNull($batch);
        $this->assertSame($single->keys()->sort()->values()->all(), $batch->keys()->sort()->values()->all());
        $this->assertSame(
            $single->get((int) $this->center->id),
            $batch->get((int) $this->center->id),
        );
    }

    /** @test */
    public function batch_incomplete_periods_does_not_grow_indicator_queries_with_page_size(): void
    {
        $type = $this->indicator->indicatorType;
        $indicatorIds = [(int) $this->indicator->id];

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 4,
            'valor' => 1,
        ]));

        for ($i = 0; $i < 19; $i++) {
            $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
                'indicator_type_id' => $type->id,
                'year_id' => $this->year->id,
                'clave' => 'MET-INV-'.($i + 1),
                'nombre' => 'Indicador inv '.$i,
                'estado' => true,
                'orden' => 100 + $i,
            ]));

            $fieldA = MetDataField::unguarded(fn () => MetDataField::create([
                'met_indicator_id' => $indicator->id,
                'label' => 'Numerador',
                'componente' => 'numerador',
                'input_type' => 'mensual',
                'aggregation_type' => 'sumatoria',
                'orden' => 1,
                'variable_key' => 'A',
            ]));

            MetDataField::unguarded(fn () => MetDataField::create([
                'met_indicator_id' => $indicator->id,
                'label' => 'Denominador',
                'componente' => 'denominador',
                'input_type' => 'mensual',
                'aggregation_type' => 'sumatoria',
                'orden' => 2,
                'variable_key' => 'B',
            ]));

            MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
                'met_data_field_id' => $fieldA->id,
                'cen_center_id' => $this->center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'mes' => 4,
                'valor' => 2,
            ]));

            $indicatorIds[] = (int) $indicator->id;
        }

        $countFor = function (array $ids): array {
            $this->app->forgetInstance(MetIndicatorIncompletePeriodService::class);

            $set = MetIndicator::query()->whereKey($ids)->with(['dataFields', 'indicatorType'])->get();

            DB::flushQueryLog();
            DB::enableQueryLog();

            app(MetIndicatorIncompletePeriodService::class)->incompletePeriodsByIndicators(
                $set,
                1,
                (int) $this->year->id,
                [(int) $this->center->id],
            );

            $log = DB::getQueryLog();
            DB::disableQueryLog();

            $indicatorsQ = collect($log)->filter(
                fn (array $entry): bool => str_contains(strtolower($entry['query']), 'from `met_indicators`')
                    || str_contains(strtolower($entry['query']), 'from "met_indicators"'),
            )->count();

            $fieldsQ = collect($log)->filter(
                fn (array $entry): bool => str_contains(strtolower($entry['query']), 'met_data_fields'),
            )->count();

            return ['indicators' => $indicatorsQ, 'fields' => $fieldsQ, 'total' => count($log)];
        };

        $with5 = $countFor(array_slice($indicatorIds, 0, 5));
        $with20 = $countFor($indicatorIds);

        $this->assertLessThanOrEqual(1, $with5['indicators']);
        $this->assertLessThanOrEqual(1, $with20['indicators']);
        $this->assertLessThanOrEqual($with5['fields'] + 1, $with20['fields']);
        $this->assertLessThanOrEqual($with5['total'] + 1, $with20['total']);
    }
}
