<?php

namespace Tests\Unit\Services\Cen;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Enums\Cen\CentroPertenenciaSnss;
use App\Models\CenHieDependency;
use App\Services\Cen\CenCenterNationalCatalogMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CenCenterNationalCatalogMapperChaitenRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_maps_hospital_chaiten_api_record_without_skip(): void
    {
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '24',
            'nombre' => 'Servicio de Salud Del Reloncaví',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $record = [
            'EstablecimientoCodigoAntiguo' => '24-135',
            'EstablecimientoCodigo' => '124135',
            'SeremiSaludCodigo_ServicioDeSaludCodigo' => '24',
            'TipoPertenenciaEstabGlosa' => 'Perteneciente al Sistema Nacional de Servicios de Salud',
            'TipoEstablecimientoGlosa' => 'Hospital',
            'AmbitoFuncionamiento' => 'Establecimiento de Salud',
            'EstablecimientoGlosa' => 'Hospital Dr Pedro Jiménez Romero (Chaitén)',
            'Certificacion' => 'No Aplica',
            'DependenciaAdministrativa' => 'Servicio de Salud',
            'NivelAtencionEstabglosa' => 'Secundario',
            'ComunaCodigo' => '10401',
            'TieneServicioUrgencia' => 'NO',
            'EstadoFuncionamiento' => 'Cerrado',
        ];

        $mapped = app(CenCenterNationalCatalogMapper::class)->map($record);

        $this->assertFalse($mapped->skipped, $mapped->skipReason ?? 'no reason');
        $this->assertSame('124135', $mapped->codigo);
        $this->assertSame('Perteneciente', $mapped->centerAttributes['pertenencia_snss']);
        $this->assertSame(CentroEstadoFuncionamiento::Cerrado->value, $mapped->centerAttributes['estado_funcionamiento']);
        $this->assertSame(CentroPertenenciaSnss::Perteneciente->value, $mapped->centerAttributes['pertenencia_snss']);
        $this->assertSame('Secundario', $mapped->centerAttributes['nivel_atencion']);
        $this->assertSame('No', $mapped->centerAttributes['tiene_urgencia']);

        $warningCodes = array_column($mapped->warnings, 'code');
        $this->assertNotContains('enum_desconocido', $warningCodes);
        $this->assertNotContains('unmapped_enum', $warningCodes);
    }

    public function test_maps_record_when_catalog_ss_codigo_is_zero_padded(): void
    {
        $dependency = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '5',
            'nombre' => 'Servicio de Salud Coquimbo',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $record = [
            'EstablecimientoCodigo' => '200723',
            'SeremiSaludCodigo_ServicioDeSaludCodigo' => '05',
            'TipoPertenenciaEstabGlosa' => 'Perteneciente al Sistema Nacional de Servicios de Salud',
            'TipoEstablecimientoGlosa' => 'Hospital',
            'AmbitoFuncionamiento' => 'Establecimiento de Salud',
            'EstablecimientoGlosa' => 'Hospital Ovalle Antiguo',
            'Certificacion' => 'No Aplica',
            'DependenciaAdministrativa' => 'Servicio de Salud',
            'NivelAtencionEstabglosa' => 'Secundario',
            'TieneServicioUrgencia' => 'No',
            'EstadoFuncionamiento' => 'Vigente en Operación Habitual',
        ];

        $mapped = app(CenCenterNationalCatalogMapper::class)->map($record);

        $this->assertFalse($mapped->skipped, $mapped->skipReason ?? 'no reason');
        $this->assertSame($dependency->id, $mapped->centerAttributes['cen_hie_dependency_id']);
    }
}
