<?php

namespace Tests\Unit\Services\Metas;

use App\Enums\IndicatorType\EtapaVigente;
use App\Jobs\MetCalculateIndicatorScopeJob;
use App\Models\CenHieDependency;
use App\Models\IndicatorTypeSetting;
use App\Models\Year;
use App\Services\Metas\MetRecalculationDispatcher;
use App\Services\Metas\MetRecalculationScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MetRecalculationDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private MetRecalculationDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        Cache::flush();
        Bus::fake();
        config(['centinela.metas.recalculation_scope_enabled' => true]);
        config(['centinela.metas.recalculation_debounce_seconds' => 90]);

        $this->dispatcher = app(MetRecalculationDispatcher::class);
    }

    /** @test */
    public function schedule_scope_despacha_job_cuando_etapa_es_autoevaluacion(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $type = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $type->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => EtapaVigente::Autoevaluacion,
            'habilitado' => true,
        ]));

        $scope = new MetRecalculationScope(
            yearId: $year->id,
            dependencyId: 1,
            indicatorTypeId: $type->id,
            centerId: 10,
            metIndicatorId: 20,
        );

        $this->assertTrue($this->dispatcher->scheduleAutoevaluacionScope($scope));

        Bus::assertDispatched(MetCalculateIndicatorScopeJob::class);
    }

    /** @test */
    public function schedule_scope_no_despacha_si_etapa_no_es_autoevaluacion(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2027, 'default' => 0]));
        $type = $this->indicatorTypeByRoute(route: 'metas-19664', attributes: ['estado' => true]);

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $type->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => EtapaVigente::Preliminar,
            'habilitado' => true,
        ]));

        $scope = new MetRecalculationScope(
            yearId: $year->id,
            dependencyId: 1,
            indicatorTypeId: $type->id,
            centerId: 10,
            metIndicatorId: 20,
        );

        $this->assertFalse($this->dispatcher->scheduleAutoevaluacionScope($scope));

        Bus::assertNotDispatched(MetCalculateIndicatorScopeJob::class);
    }

    /** @test */
    public function schedule_scope_respeta_debounce(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2028, 'default' => 0]));
        $type = $this->indicatorTypeByRoute(route: 'metas-20707', attributes: ['estado' => true]);

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $type->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => EtapaVigente::Autoevaluacion,
            'habilitado' => true,
        ]));

        $scope = new MetRecalculationScope(
            yearId: $year->id,
            dependencyId: 1,
            indicatorTypeId: $type->id,
            centerId: 10,
            metIndicatorId: 20,
        );

        $this->dispatcher->scheduleAutoevaluacionScope($scope);
        $this->dispatcher->scheduleAutoevaluacionScope($scope);

        Bus::assertDispatchedTimes(MetCalculateIndicatorScopeJob::class, 1);
    }

    /** @test */
    public function schedule_scope_respeta_flag_disabled(): void
    {
        config(['centinela.metas.recalculation_scope_enabled' => false]);

        $scope = new MetRecalculationScope(
            yearId: 1,
            dependencyId: 1,
            indicatorTypeId: 1,
            centerId: 10,
            metIndicatorId: 20,
        );

        $this->assertFalse($this->dispatcher->scheduleAutoevaluacionScope($scope));

        Bus::assertNotDispatched(MetCalculateIndicatorScopeJob::class);
    }
}
