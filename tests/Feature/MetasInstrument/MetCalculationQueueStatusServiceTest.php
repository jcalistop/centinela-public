<?php

namespace Tests\Feature\MetasInstrument;

use App\Jobs\MetCalculateIndicatorScopeJob;
use App\Jobs\MetCalculateResultsJob;
use App\Models\IndicatorType;
use App\Models\Year;
use App\Services\Metas\MetCalculationQueueStatusService;
use App\Services\Metas\MetRecalculationScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MetCalculationQueueStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    private MetCalculationQueueStatusService $service;

    private IndicatorType $indicatorType;

    private Year $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MetCalculationQueueStatusService::class);
        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);
    }

    /** @test */
    public function sin_trabajos_devuelve_cero(): void
    {
        $status = $this->service->statusForScope(1, $this->indicatorType->id);

        $this->assertSame(0, $status['pending_jobs']);
        $this->assertNull($status['message']);
    }

    /** @test */
    public function cuenta_trabajos_de_calculo_del_ss_e_instrumento(): void
    {
        $this->queueDatabaseJob(new MetCalculateResultsJob(
            $this->year->id,
            1,
            $this->indicatorType->id,
            'AUTOEVALUACION',
        ));

        $this->queueDatabaseJob(new MetCalculateResultsJob(
            $this->year->id,
            2,
            $this->indicatorType->id,
            'AUTOEVALUACION',
        ));

        $status = $this->service->statusForScope(1, $this->indicatorType->id);

        $this->assertSame(1, $status['pending_jobs']);
        $this->assertSame(
            'Existen 1 proceso de cálculo en cola que se ejecutarán en los próximos 2 minutos.',
            $status['message'],
        );
    }

    /** @test */
    public function cuenta_trabajos_de_alcance_por_indicador(): void
    {
        $scope = new MetRecalculationScope(
            yearId: $this->year->id,
            dependencyId: 1,
            indicatorTypeId: $this->indicatorType->id,
            centerId: 10,
            metIndicatorId: 20,
            measurementUnitId: null,
        );

        $this->queueDatabaseJob(new MetCalculateIndicatorScopeJob($scope));

        $status = $this->service->statusForScope(1, $this->indicatorType->id);

        $this->assertSame(1, $status['pending_jobs']);
        $this->assertStringContainsString('Existen 1 proceso de cálculo en cola', $status['message'] ?? '');
    }

    /** @test */
    public function pluraliza_mensaje_con_varios_trabajos(): void
    {
        $this->queueDatabaseJob(new MetCalculateResultsJob(
            $this->year->id,
            1,
            $this->indicatorType->id,
            'AUTOEVALUACION',
        ));

        $this->queueDatabaseJob(new MetCalculateResultsJob(
            $this->year->id,
            1,
            $this->indicatorType->id,
            'PRELIMINAR',
        ));

        $status = $this->service->statusForScope(1, $this->indicatorType->id);

        $this->assertSame(2, $status['pending_jobs']);
        $this->assertSame(
            'Existen 2 procesos de cálculo en cola que se ejecutarán en los próximos 4 minutos.',
            $status['message'],
        );
    }

    private function queueDatabaseJob(object $job): void
    {
        Queue::connection('database')->push($job);

        $this->assertGreaterThan(
            0,
            DB::table('jobs')->count(),
            'No se encoló el job en la tabla jobs.',
        );
    }
}
