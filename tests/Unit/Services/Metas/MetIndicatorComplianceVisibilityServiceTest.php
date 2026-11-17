<?php

namespace Tests\Unit\Services\Metas;

use App\Enums\Metas\MetDataLoadStatus;
use App\Models\MetIndicator;
use App\Services\Metas\MetIndicatorComplianceVisibilityService;
use Tests\TestCase;

class MetIndicatorComplianceVisibilityServiceTest extends TestCase
{
    /** @test */
    public function resultado_nulo_es_sin_datos_y_no_tiene_cumplimiento(): void
    {
        $service = app(MetIndicatorComplianceVisibilityService::class);

        $this->assertTrue($service->sinDatos(null, MetDataLoadStatus::Cargado, true));
        $this->assertNull(
            $service->cumplimientoVisible($this->indicator('>='), null, 20.0, MetDataLoadStatus::Cargado, true),
        );
    }

    /** @test */
    public function sin_datos_con_campos_rastreables_oculta_el_cumplimiento_en_ambas_polaridades(): void
    {
        $service = app(MetIndicatorComplianceVisibilityService::class);

        foreach (['>=', '<=', '<'] as $operador) {
            $this->assertTrue($service->sinDatos(0.0, MetDataLoadStatus::SinDatos, true));
            $this->assertNull(
                $service->cumplimientoVisible($this->indicator($operador), 0.0, 100.0, MetDataLoadStatus::SinDatos, true),
                'Sin carga no debe fabricar 0 % ni 100 % con operador '.$operador,
            );
        }
    }

    /** @test */
    public function error_de_calculo_con_campos_rastreables_oculta_el_cumplimiento(): void
    {
        $service = app(MetIndicatorComplianceVisibilityService::class);

        $this->assertTrue($service->sinDatos(12.0, MetDataLoadStatus::ErrorCalculo, true));
        $this->assertNull(
            $service->cumplimientoVisible($this->indicator('>='), 12.0, 100.0, MetDataLoadStatus::ErrorCalculo, true),
        );
    }

    /** @test */
    public function sin_datos_sin_campos_rastreables_delega_en_la_formula(): void
    {
        $service = app(MetIndicatorComplianceVisibilityService::class);

        $this->assertFalse($service->sinDatos(0.0, MetDataLoadStatus::SinDatos, false));
        $this->assertSame(
            0.0,
            $service->cumplimientoVisible($this->indicator('>='), 0.0, 100.0, MetDataLoadStatus::SinDatos, false),
        );
    }

    /** @test */
    public function parcial_muestra_cumplimiento(): void
    {
        $service = app(MetIndicatorComplianceVisibilityService::class);

        $this->assertFalse($service->sinDatos(5.0, MetDataLoadStatus::Parcial, true));
        $this->assertSame(
            50.0,
            $service->cumplimientoVisible($this->indicator('>='), 5.0, 10.0, MetDataLoadStatus::Parcial, true),
        );
    }

    /** @test */
    public function resultado_cero_con_carga_conserva_la_polaridad_del_indicador(): void
    {
        $service = app(MetIndicatorComplianceVisibilityService::class);

        $this->assertFalse($service->sinDatos(0.0, MetDataLoadStatus::Cargado, true));
        $this->assertSame(
            0.0,
            $service->cumplimientoVisible($this->indicator('>='), 0.0, 100.0, MetDataLoadStatus::Cargado, true),
        );
        $this->assertSame(
            100.0,
            $service->cumplimientoVisible($this->indicator('<='), 0.0, 100.0, MetDataLoadStatus::Cargado, true),
        );
    }

    /** @test */
    public function resultado_real_con_carga_es_lineal_y_topea_en_100(): void
    {
        $service = app(MetIndicatorComplianceVisibilityService::class);

        $this->assertSame(
            50.0,
            $service->cumplimientoVisible($this->indicator('>='), 5.0, 10.0, MetDataLoadStatus::Cargado, true),
        );
        $this->assertSame(
            50.0,
            $service->cumplimientoVisible($this->indicator('<='), 20.0, 10.0, MetDataLoadStatus::Cargado, true),
        );
        $this->assertSame(
            100.0,
            $service->cumplimientoVisible($this->indicator('>='), 30.0, 10.0, MetDataLoadStatus::Cargado, true),
        );
    }

    /** @test */
    public function meta_efectiva_no_positiva_no_tiene_cumplimiento(): void
    {
        $service = app(MetIndicatorComplianceVisibilityService::class);

        $this->assertNull(
            $service->cumplimientoVisible($this->indicator('>='), 10.0, 0.0, MetDataLoadStatus::Cargado, true),
        );
    }

    private function indicator(string $operador): MetIndicator
    {
        $indicator = new MetIndicator;
        $indicator->meta_operador = $operador;

        return $indicator;
    }
}
