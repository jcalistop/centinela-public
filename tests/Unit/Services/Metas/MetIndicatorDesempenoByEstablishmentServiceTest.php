<?php

namespace Tests\Unit\Services\Metas;

use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\MetCenterAdosado;
use App\Models\MetDataField;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetMonthlyData;
use App\Models\MetResult;
use App\Models\Year;
use App\Services\Metas\MetIndicatorCalculationDataScopeService;
use App\Services\Metas\MetIndicatorDesempenoByEstablishmentService;
use App\Services\Metas\MetIndicatorLastLoadedMonthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MetIndicatorDesempenoByEstablishmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $indicatorType;

    private CenCenter $center;

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

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $this->center = $this->createMinimalCenCenter([
            'id' => 10,
            'codigo' => 'CEN-10',
            'nombre_corto' => 'Hospital Test',
            'cen_hie_dependency_id' => 1,
        ]);

        $this->indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'MET-CUMPL-UT',
            'nombre' => 'Indicador cumplimiento UT',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'meta_valor' => 100,
            'meta_operador' => '>=',
            'year_id' => $this->year->id,
            'estado' => true,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Numerador',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 0,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Denominador',
            'variable_key' => 'B',
            'componente' => 'denominador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 1,
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'aplica' => true,
            'seleccionado' => false,
        ]));

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'ANUAL',
            'numerador' => 0,
            'denominador' => 0,
            'resultado' => 0,
            'cumple_meta' => false,
        ]));
    }

    /** @test */
    public function promedio_cumplimiento_es_null_sin_carga_mensual_aunque_resultado_sea_cero(): void
    {
        $payload = app(MetIndicatorDesempenoByEstablishmentService::class)->buildForIndicator(
            $this->indicator,
            1,
            (int) $this->year->id,
        );

        $this->assertNull($payload['promedio_cumplimiento']);
        $this->assertNull($payload['rows'][0]['cumplimiento']);
        $this->assertSame('sin_datos', $payload['rows'][0]['estado_carga']);
    }

    /** @test */
    public function promedio_cumplimiento_calcula_cero_cuando_hay_datos_y_resultado_es_cero(): void
    {
        $fields = MetDataField::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->get()
            ->keyBy('variable_key');

        foreach (['A' => 0, 'B' => 100] as $key => $valor) {
            MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
                'met_data_field_id' => $fields[$key]->id,
                'cen_center_id' => $this->center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'mes' => 1,
                'valor' => $valor,
            ]));
        }

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'MES-01',
            'numerador' => 0,
            'denominador' => 100,
            'resultado' => 0,
            'cumple_meta' => false,
        ]));

        $payload = app(MetIndicatorDesempenoByEstablishmentService::class)->buildForIndicator(
            $this->indicator->fresh(['dataFields', 'indicatorType']),
            1,
            (int) $this->year->id,
        );

        $this->assertSame(0.0, $payload['promedio_cumplimiento']);
        $this->assertSame(0.0, $payload['rows'][0]['cumplimiento']);
        $this->assertSame('cargado', $payload['rows'][0]['estado_carga']);
    }

    /** @test */
    public function cumplimiento_muestra_valor_lineal_aunque_cruce_umbrales(): void
    {
        $this->indicator->update([
            'umbral_inferior' => 99.49,
            'umbral_superior' => 99.50,
            'meta_valor' => 100,
        ]);

        MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->update(['meta_efectiva' => 100]);

        $fields = MetDataField::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->get()
            ->keyBy('variable_key');

        foreach (['A' => 99.29, 'B' => 100] as $key => $valor) {
            MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
                'met_data_field_id' => $fields[$key]->id,
                'cen_center_id' => $this->center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'mes' => 1,
                'valor' => $valor,
            ]));
        }

        MetResult::query()->where('met_indicator_id', $this->indicator->id)->delete();

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'MES-01',
            'numerador' => 99.29,
            'denominador' => 100,
            'resultado' => 99.29,
            'cumple_meta' => false,
        ]));

        $payload = app(MetIndicatorDesempenoByEstablishmentService::class)->buildForIndicator(
            $this->indicator->fresh(['dataFields', 'indicatorType']),
            1,
            (int) $this->year->id,
        );

        $this->assertEqualsWithDelta(99.29, $payload['rows'][0]['cumplimiento'], 0.0001);
        $this->assertSame(99.49, $payload['umbral_inferior']);
        $this->assertSame(99.5, $payload['umbral_superior']);
    }

    /** @test */
    public function agrupa_ss_sin_datos_locales_marca_cargado_y_cumplimiento_desde_universo_ss(): void
    {
        $peer = $this->createMinimalCenCenter([
            'id' => 11,
            'codigo' => 'CEN-11',
            'nombre_corto' => 'Hospital Peer',
            'cen_hie_dependency_id' => 1,
        ]);

        MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->where('cen_center_id', $this->center->id)
            ->update([
                'agrupa_ss' => true,
                'meta_efectiva' => 60,
            ]);

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $peer->id,
            'aplica' => true,
            'agrupa_ss' => false,
            'seleccionado' => false,
            'meta_efectiva' => 60,
        ]));

        $this->indicator->update(['meta_valor' => 60, 'meta_operador' => '>=']);

        $fields = MetDataField::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->get()
            ->keyBy('variable_key');

        foreach (['A' => 30, 'B' => 60] as $key => $valor) {
            MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
                'met_data_field_id' => $fields[$key]->id,
                'cen_center_id' => $peer->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'mes' => 1,
                'valor' => $valor,
            ]));
        }

        MetResult::query()->where('met_indicator_id', $this->indicator->id)->delete();

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'MES-01',
            'numerador' => 30,
            'denominador' => 60,
            'resultado' => 50,
            'cumple_meta' => false,
        ]));

        $payload = app(MetIndicatorDesempenoByEstablishmentService::class)->buildForIndicator(
            $this->indicator->fresh(['dataFields', 'indicatorType']),
            1,
            (int) $this->year->id,
        );

        $madreRow = collect($payload['rows'])->firstWhere('cen_center_id', $this->center->id);

        $this->assertNotNull($madreRow);
        $this->assertTrue($madreRow['agrupa_ss']);
        $this->assertSame('cargado', $madreRow['estado_carga']);
        $this->assertNotNull($madreRow['cumplimiento']);
        $this->assertGreaterThan(0, $madreRow['cumplimiento']);
    }

    /** @test */
    public function build_batch_for_indicators_matches_build_for_indicator_promedios(): void
    {
        $second = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'MET-CUMPL-UT-2',
            'nombre' => 'Indicador cumplimiento UT 2',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'meta_valor' => 100,
            'meta_operador' => '>=',
            'year_id' => $this->year->id,
            'estado' => true,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $second->id,
            'label' => 'Numerador',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 0,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $second->id,
            'label' => 'Denominador',
            'variable_key' => 'B',
            'componente' => 'denominador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 1,
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $second->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'aplica' => true,
            'seleccionado' => false,
        ]));

        foreach ([$this->indicator, $second] as $indicator) {
            $fields = MetDataField::query()
                ->where('met_indicator_id', $indicator->id)
                ->get()
                ->keyBy('variable_key');

            foreach (['A' => 80, 'B' => 100] as $key => $valor) {
                MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
                    'met_data_field_id' => $fields[$key]->id,
                    'cen_center_id' => $this->center->id,
                    'cen_hie_dependency_id' => 1,
                    'year_id' => $this->year->id,
                    'mes' => 1,
                    'valor' => $valor,
                ]));
            }

            MetResult::query()->where('met_indicator_id', $indicator->id)->delete();

            MetResult::unguarded(fn () => MetResult::create([
                'met_indicator_id' => $indicator->id,
                'cen_center_id' => $this->center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'periodo' => 'MES-01',
                'numerador' => 80,
                'denominador' => 100,
                'resultado' => 80,
                'cumple_meta' => true,
            ]));
        }

        $indicators = MetIndicator::query()
            ->whereIn('id', [$this->indicator->id, $second->id])
            ->with(['dataFields', 'indicatorType', 'formula'])
            ->orderBy('id')
            ->get();

        $service = app(MetIndicatorDesempenoByEstablishmentService::class);
        $batch = $service->buildBatchForIndicators($indicators, 1, (int) $this->year->id);

        foreach ($indicators as $indicator) {
            $single = $service->buildForIndicator(
                $indicator->fresh(['dataFields', 'indicatorType']),
                1,
                (int) $this->year->id,
            );

            $this->assertArrayHasKey($indicator->id, $batch);
            $this->assertSame(
                $single['promedio_cumplimiento'],
                $batch[$indicator->id]['promedio_cumplimiento'],
                "Paridad de promedio_cumplimiento para indicador {$indicator->id}",
            );
        }
    }

    /** @test */
    public function batch_adosados_y_pool_no_multiplican_queries_por_centros(): void
    {
        $fields = MetDataField::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->get()
            ->keyBy('variable_key');

        $centerIds = [(int) $this->center->id];

        for ($i = 1; $i <= 19; $i++) {
            $center = $this->createMinimalCenCenter([
                'id' => 100 + $i,
                'codigo' => 'CEN-'.(100 + $i),
                'nombre_corto' => 'Centro '.$i,
                'cen_hie_dependency_id' => 1,
            ]);
            $centerIds[] = (int) $center->id;

            $adosado = $this->createMinimalCenCenter([
                'id' => 200 + $i,
                'codigo' => 'ADO-'.(200 + $i),
                'nombre_corto' => 'Adosado '.$i,
                'cen_hie_dependency_id' => 1,
            ]);

            MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
                'met_indicator_id' => $this->indicator->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => 1,
                'cen_center_id' => $center->id,
                'aplica' => true,
                'seleccionado' => false,
            ]));

            MetCenterAdosado::unguarded(fn () => MetCenterAdosado::create([
                'cen_center_madre_id' => $center->id,
                'cen_center_adosado_id' => $adosado->id,
                'year_id' => $this->year->id,
                'indicator_type_id' => $this->indicatorType->id,
                'cen_hie_dependency_id' => 1,
            ]));

            foreach (['A' => 1, 'B' => 2] as $key => $valor) {
                MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
                    'met_data_field_id' => $fields[$key]->id,
                    'cen_center_id' => $center->id,
                    'cen_hie_dependency_id' => 1,
                    'year_id' => $this->year->id,
                    'mes' => 3,
                    'valor' => $valor,
                ]));
            }
        }

        $indicator = $this->indicator->fresh(['dataFields', 'indicatorType']);

        $countQueries = function (int $takeCenters) use ($indicator, $centerIds): array {
            // Rebind services to clear request-scoped memos between measurements.
            $this->app->forgetInstance(MetIndicatorDesempenoByEstablishmentService::class);
            $this->app->forgetInstance(MetIndicatorCalculationDataScopeService::class);
            $this->app->forgetInstance(MetIndicatorLastLoadedMonthService::class);
            $fresh = app(MetIndicatorDesempenoByEstablishmentService::class);

            MetIndicatorConfiguration::query()
                ->where('met_indicator_id', $indicator->id)
                ->update(['aplica' => false]);

            MetIndicatorConfiguration::query()
                ->where('met_indicator_id', $indicator->id)
                ->whereIn('cen_center_id', array_slice($centerIds, 0, $takeCenters))
                ->update(['aplica' => true]);

            DB::flushQueryLog();
            DB::enableQueryLog();

            $fresh->buildBatchForIndicators(
                MetIndicator::query()
                    ->whereKey([$indicator->id])
                    ->with(['dataFields', 'indicatorType', 'formula'])
                    ->get(),
                1,
                (int) $this->year->id,
            );

            $log = DB::getQueryLog();
            DB::disableQueryLog();

            $adosados = collect($log)->filter(
                fn (array $entry): bool => str_contains(strtolower($entry['query']), 'met_center_adosados'),
            )->count();

            $monthly = collect($log)->filter(
                fn (array $entry): bool => str_contains(strtolower($entry['query']), 'met_monthly_data'),
            )->count();

            return ['adosados' => $adosados, 'monthly' => $monthly, 'total' => count($log)];
        };

        $with5 = $countQueries(5);
        $with20 = $countQueries(20);

        $this->assertLessThanOrEqual(2, $with5['adosados'], 'adosados con 5 centros: '.$with5['adosados']);
        $this->assertLessThanOrEqual(2, $with20['adosados'], 'adosados con 20 centros: '.$with20['adosados']);
        $this->assertSame(
            $with5['adosados'],
            $with20['adosados'],
            'met_center_adosados no debe crecer con el número de centros',
        );
        $this->assertLessThanOrEqual(
            $with5['monthly'] + 2,
            $with20['monthly'],
            'met_monthly_data no debe crecer con centros (tol ±2). 5='.$with5['monthly'].' 20='.$with20['monthly'],
        );
        $this->assertLessThanOrEqual(
            $with5['total'] + 2,
            $with20['total'],
            'query_count no debe crecer con centros (tol ±2). 5='.$with5['total'].' 20='.$with20['total'],
        );
    }

    /** @test */
    public function snapshot_con_adosado_incluye_datos_del_adosado_en_estado_carga(): void
    {
        $adosado = $this->createMinimalCenCenter([
            'id' => 12,
            'codigo' => 'CEN-12',
            'nombre_corto' => 'Adosado',
            'cen_hie_dependency_id' => 1,
        ]);

        MetCenterAdosado::unguarded(fn () => MetCenterAdosado::create([
            'cen_center_madre_id' => $this->center->id,
            'cen_center_adosado_id' => $adosado->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $fields = MetDataField::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->get()
            ->keyBy('variable_key');

        foreach (['A' => 10, 'B' => 20] as $key => $valor) {
            MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
                'met_data_field_id' => $fields[$key]->id,
                'cen_center_id' => $adosado->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'mes' => 4,
                'valor' => $valor,
            ]));
        }

        $payload = app(MetIndicatorDesempenoByEstablishmentService::class)->buildForIndicator(
            $this->indicator->fresh(['dataFields', 'indicatorType']),
            1,
            (int) $this->year->id,
        );

        $row = collect($payload['rows'])->firstWhere('cen_center_id', $this->center->id);

        $this->assertNotNull($row);
        $this->assertSame('cargado', $row['estado_carga']);
        $this->assertSame(4, $row['ultimo_mes_cargado']);
    }
}
