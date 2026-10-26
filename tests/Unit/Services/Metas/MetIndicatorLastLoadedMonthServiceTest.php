<?php

namespace Tests\Unit\Services\Metas;

use App\Enums\Metas\MetDataLoadStatus;
use App\Models\CenHieDependency;
use App\Models\MetCenterAdosado;
use App\Models\MetDataField;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetMonthlyData;
use App\Models\MetResult;
use App\Models\Year;
use App\Services\Metas\MetIndicatorLastLoadedMonthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MetIndicatorLastLoadedMonthServiceTest extends TestCase
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
            'nombre' => 'SS Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));
    }

    /** @test */
    public function last_month_by_indicator_ids_returns_max_mes_per_indicator(): void
    {
        $indicatorType = $this->indicatorTypeByRoute();

        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $indicatorType->id,
            'year_id' => $this->year->id,
            'clave' => 'IND-01',
            'nombre' => 'Indicador test',
            'estado' => true,
        ]));

        $field = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Campo A',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 0,
        ]));

        $center = $this->createMinimalCenCenter([
            'codigo' => 'C01',
            'nombre_oficial' => 'Centro test',
            'nombre_corto' => 'Centro',
        ]);

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $field->id,
            'cen_center_id' => $center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 2,
            'valor' => 1,
        ]));

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $field->id,
            'cen_center_id' => $center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 7,
            'valor' => 2,
        ]));

        $service = app(MetIndicatorLastLoadedMonthService::class);

        $map = $service->lastMonthByIndicatorIds([$indicator->id], 1, $this->year->id);

        $this->assertSame(7, $map->get($indicator->id));
        $this->assertSame('Jul', $service->etiquetaMes(7));
        $this->assertSame('Sin datos', $service->etiquetaMes(null));
    }

    /** @test */
    public function last_month_by_indicator_and_center_ids_returns_max_mes_per_center(): void
    {
        $indicatorType = $this->indicatorTypeByRoute();

        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $indicatorType->id,
            'year_id' => $this->year->id,
            'clave' => 'IND-02',
            'nombre' => 'Indicador por centro',
            'estado' => true,
        ]));

        $field = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Campo A',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 0,
        ]));

        $centerA = $this->createMinimalCenCenter(['codigo' => 'CA', 'nombre_corto' => 'Centro A']);
        $centerB = $this->createMinimalCenCenter(['codigo' => 'CB', 'nombre_corto' => 'Centro B']);

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $field->id,
            'cen_center_id' => $centerA->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 6,
            'valor' => 10,
        ]));

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $field->id,
            'cen_center_id' => $centerB->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 9,
            'valor' => 20,
        ]));

        $service = app(MetIndicatorLastLoadedMonthService::class);

        $map = $service->lastMonthByIndicatorAndCenterIds(
            $indicator->id,
            [$centerA->id, $centerB->id],
            1,
            $this->year->id,
        );

        $this->assertSame(6, $map->get($centerA->id));
        $this->assertSame(9, $map->get($centerB->id));
    }

    /** @test */
    public function snapshots_marcan_parcial_cuando_falta_campo_en_ultimo_mes(): void
    {
        $indicatorType = $this->indicatorTypeByRoute();

        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $indicatorType->id,
            'year_id' => $this->year->id,
            'clave' => 'IND-03',
            'nombre' => 'Indicador parcial',
            'estado' => true,
        ]));

        $numerador = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Numerador',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 0,
        ]));

        $denominador = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Denominador',
            'variable_key' => 'B',
            'componente' => 'denominador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 1,
        ]));

        $center = $this->createMinimalCenCenter(['codigo' => 'CP', 'nombre_corto' => 'Centro parcial']);

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $denominador->id,
            'cen_center_id' => $center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 12,
            'valor' => 100,
        ]));

        $service = app(MetIndicatorLastLoadedMonthService::class);

        $snapshot = $service->snapshotsByIndicatorAndCenterIds(
            $indicator,
            [$center->id],
            1,
            $this->year->id,
        )->get($center->id);

        $this->assertSame(12, $snapshot['ultimo_mes_cargado']);
        $this->assertSame(MetDataLoadStatus::Parcial, $snapshot['estado_carga']);
    }

    /** @test */
    public function snapshots_marcan_error_calculo_con_prioridad_sobre_parcial(): void
    {
        $indicatorType = $this->indicatorTypeByRoute();

        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $indicatorType->id,
            'year_id' => $this->year->id,
            'clave' => 'IND-04',
            'nombre' => 'Indicador error',
            'estado' => true,
        ]));

        $field = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Campo A',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 0,
        ]));

        $center = $this->createMinimalCenCenter(['codigo' => 'CE', 'nombre_corto' => 'Centro error']);

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $field->id,
            'cen_center_id' => $center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 3,
            'valor' => 5,
        ]));

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $indicator->id,
            'cen_center_id' => $center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'MES-03',
            'numerador' => null,
            'denominador' => null,
            'resultado' => null,
            'error_message' => 'Divisor es cero',
        ]));

        $service = app(MetIndicatorLastLoadedMonthService::class);

        $snapshot = $service->snapshotsByIndicatorAndCenterIds(
            $indicator,
            [$center->id],
            1,
            $this->year->id,
            true,
            collect([$center->id => ['count' => 1, 'periodos' => ['MES-03'], 'message' => 'Divisor es cero']]),
        )->get($center->id);

        $this->assertSame(3, $snapshot['ultimo_mes_cargado']);
        $this->assertSame(MetDataLoadStatus::ErrorCalculo, $snapshot['estado_carga']);
    }

    /** @test */
    public function snapshots_por_centro_usan_el_universo_cuando_reciben_configuraciones(): void
    {
        $indicatorType = $this->indicatorTypeByRoute();

        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $indicatorType->id,
            'year_id' => $this->year->id,
            'clave' => 'IND-05',
            'nombre' => 'Indicador con adosado',
            'estado' => true,
        ]));

        $field = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Campo A',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 0,
        ]));

        $madre = $this->createMinimalCenCenter(['codigo' => 'CM', 'nombre_corto' => 'Centro madre']);
        $adosado = $this->createMinimalCenCenter(['codigo' => 'CAD', 'nombre_corto' => 'Centro adosado']);

        MetCenterAdosado::unguarded(fn () => MetCenterAdosado::create([
            'cen_center_madre_id' => $madre->id,
            'cen_center_adosado_id' => $adosado->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $config = MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $madre->id,
            'aplica' => true,
            'agrupa_ss' => false,
            'seleccionado' => false,
        ]));

        // Solo el adosado tiene carga; la madre no tiene ninguna fila propia.
        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $field->id,
            'cen_center_id' => $adosado->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 6,
            'valor' => 15,
        ]));

        $indicators = collect([$indicator->fresh(['dataFields', 'indicatorType'])]);

        $conConfigs = app(MetIndicatorLastLoadedMonthService::class)->snapshotsByCenterAndIndicators(
            $madre->id,
            $indicators,
            1,
            $this->year->id,
            true,
            null,
            collect([$indicator->id => $config]),
        )->get($indicator->id);

        $this->assertSame(6, $conConfigs['ultimo_mes_cargado']);
        $this->assertSame(MetDataLoadStatus::Cargado, $conConfigs['estado_carga']);

        $sinConfigs = app(MetIndicatorLastLoadedMonthService::class)->snapshotsByCenterAndIndicators(
            $madre->id,
            $indicators,
            1,
            $this->year->id,
        )->get($indicator->id);

        $this->assertNull($sinConfigs['ultimo_mes_cargado']);
        $this->assertSame(MetDataLoadStatus::SinDatos, $sinConfigs['estado_carga']);
    }

    /** @test */
    public function snapshots_por_centro_consultan_el_pool_una_sola_vez(): void
    {
        $indicatorType = $this->indicatorTypeByRoute();

        $center = $this->createMinimalCenCenter(['codigo' => 'CPOOL', 'nombre_corto' => 'Centro pool']);

        $indicators = collect();
        $configs = collect();

        foreach (['IND-P1', 'IND-P2', 'IND-P3'] as $clave) {
            $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
                'indicator_type_id' => $indicatorType->id,
                'year_id' => $this->year->id,
                'clave' => $clave,
                'nombre' => 'Indicador '.$clave,
                'estado' => true,
            ]));

            $field = MetDataField::unguarded(fn () => MetDataField::create([
                'met_indicator_id' => $indicator->id,
                'label' => 'Campo A',
                'variable_key' => 'A',
                'componente' => 'numerador',
                'input_type' => 'mensual',
                'aggregation_type' => 'sumatoria',
                'orden' => 0,
            ]));

            MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
                'met_data_field_id' => $field->id,
                'cen_center_id' => $center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'mes' => 4,
                'valor' => 3,
            ]));

            $configs[$indicator->id] = MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
                'met_indicator_id' => $indicator->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => 1,
                'cen_center_id' => $center->id,
                'aplica' => true,
                'agrupa_ss' => false,
                'seleccionado' => false,
            ]));

            $indicators->push($indicator->fresh(['dataFields', 'indicatorType']));
        }

        $service = app(MetIndicatorLastLoadedMonthService::class);

        DB::enableQueryLog();
        $snapshots = $service->snapshotsByCenterAndIndicators(
            $center->id,
            $indicators,
            1,
            $this->year->id,
            true,
            null,
            $configs,
        );
        $monthlyQueries = collect(DB::getQueryLog())
            ->filter(fn (array $entry): bool => str_contains($entry['query'], 'met_monthly_data'))
            ->count();
        DB::disableQueryLog();

        $this->assertCount(3, $snapshots);
        $this->assertSame(1, $monthlyQueries);

        foreach ($snapshots as $snapshot) {
            $this->assertSame(4, $snapshot['ultimo_mes_cargado']);
        }
    }
}
