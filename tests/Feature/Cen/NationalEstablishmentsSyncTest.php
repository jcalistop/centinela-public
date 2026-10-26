<?php

namespace Tests\Feature\Cen;

use App\Enums\Cen\CenCenterOrigen;
use App\Models\CenCenter;
use App\Models\CenCenterSyncRunChange;
use App\Models\CenCenterSyncRunIssue;
use App\Models\CenHieDependency;
use App\Services\Cen\CenCenterSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesMinimalCenCenter;
use Tests\TestCase;

class NationalEstablishmentsSyncTest extends TestCase
{
    use CreatesMinimalCenCenter;
    use RefreshDatabase;

    public function test_sync_skips_rows_without_ss_codigo(): void
    {
        Config::set('centinela.national_establishments.enabled', true);

        $ss = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '15',
            'nombre' => 'SS Test',
            'tipo_dependencia' => 'SERVICIO',
        ]));

        Http::fake([
            '*' => Http::response([
                'success' => true,
                'result' => [
                    'total' => 2,
                    'records' => [
                        [
                            'EstablecimientoCodigo' => '100001',
                            'EstablecimientoGlosa' => 'Hospital Test',
                            'SeremiSaludCodigo_ServicioDeSaludCodigo' => null,
                            'TipoEstablecimientoGlosa' => 'Hospital',
                            'AmbitoFuncionamiento' => 'Establecimiento de Salud',
                            'Certificacion' => 'No Aplica',
                            'DependenciaAdministrativa' => 'Servicio de Salud',
                            'TipoPertenenciaEstabGlosa' => 'Perteneciente',
                            'NivelAtencionEstabglosa' => 'Terciario',
                            'TieneServicioUrgencia' => 'Sí',
                            'TipoUrgencia' => 'Urgencia',
                            'ClasificacionTipoSapu' => 'No Aplica',
                            'TipoSistemaSaludGlosa' => 'Público',
                            'EstadoFuncionamiento' => 'Vigente en Operación Habitual',
                            'NivelComplejidadEstabGlosa' => 'Alta',
                            'TipoAtencionEstabGlosa' => 'Abierta',
                        ],
                        [
                            'EstablecimientoCodigo' => '100002',
                            'EstablecimientoGlosa' => 'CESFAM Test',
                            'SeremiSaludCodigo_ServicioDeSaludCodigo' => '15',
                            'TipoEstablecimientoGlosa' => 'Centro de Salud Familiar (CESFAM)',
                            'AmbitoFuncionamiento' => 'Establecimiento de Salud',
                            'Certificacion' => 'No Aplica',
                            'DependenciaAdministrativa' => 'Servicio de Salud',
                            'TipoPertenenciaEstabGlosa' => 'Perteneciente',
                            'NivelAtencionEstabglosa' => 'Primario',
                            'TieneServicioUrgencia' => 'No',
                            'TipoUrgencia' => 'No Aplica',
                            'ClasificacionTipoSapu' => 'No Aplica',
                            'TipoSistemaSaludGlosa' => 'Público',
                            'EstadoFuncionamiento' => 'Vigente en Operación Habitual',
                            'NivelComplejidadEstabGlosa' => 'Baja',
                            'TipoAtencionEstabGlosa' => 'Abierta',
                        ],
                    ],
                ],
            ]),
        ]);

        $run = app(CenCenterSyncService::class)->sync('15', null);

        $this->assertSame(1, $run->skipped_count);
        $this->assertSame(1, $run->created_count);
        $this->assertDatabaseHas('cen_centers', [
            'codigo' => '100002',
            'origen' => CenCenterOrigen::Api->value,
            'cen_hie_dependency_id' => $ss->id,
        ]);
        $this->assertDatabaseMissing('cen_centers', ['codigo' => '100001']);

        $this->assertDatabaseHas('cen_center_sync_run_changes', [
            'cen_center_sync_run_id' => $run->id,
            'establecimiento_codigo' => '100002',
            'action' => 'created',
        ]);
        $this->assertDatabaseHas('cen_center_sync_run_changes', [
            'cen_center_sync_run_id' => $run->id,
            'establecimiento_codigo' => '100001',
            'action' => 'skipped',
            'detail' => 'missing_ss_codigo',
        ]);
    }

    public function test_sync_does_not_update_manual_center(): void
    {
        Config::set('centinela.national_establishments.enabled', true);

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '15',
            'nombre' => 'SS Test',
            'tipo_dependencia' => 'SERVICIO',
        ]));

        $manual = $this->createMinimalCenCenter([
            'codigo' => 'MAN-1',
            'origen' => CenCenterOrigen::Manual->value,
            'nombre_oficial' => 'Manual Original',
        ]);

        Http::fake([
            '*' => Http::response([
                'success' => true,
                'result' => [
                    'total' => 0,
                    'records' => [],
                ],
            ]),
        ]);

        app(CenCenterSyncService::class)->sync('15', null);

        $this->assertSame('Manual Original', $manual->fresh()->nombre_oficial);
    }

    public function test_sync_logs_attribute_changes_on_update(): void
    {
        Config::set('centinela.national_establishments.enabled', true);

        $ss = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '15',
            'nombre' => 'SS Test',
            'tipo_dependencia' => 'SERVICIO',
        ]));

        $this->createMinimalCenCenter([
            'codigo' => '100010',
            'origen' => CenCenterOrigen::Api->value,
            'cen_hie_dependency_id' => $ss->id,
            'nombre_oficial' => 'Nombre Antiguo',
        ]);

        Http::fake([
            '*' => Http::response([
                'success' => true,
                'result' => [
                    'total' => 1,
                    'records' => [
                        [
                            'EstablecimientoCodigo' => '100010',
                            'EstablecimientoGlosa' => 'Nombre Nuevo API',
                            'SeremiSaludCodigo_ServicioDeSaludCodigo' => '15',
                            'TipoEstablecimientoGlosa' => 'Hospital',
                            'AmbitoFuncionamiento' => 'Establecimiento de Salud',
                            'Certificacion' => 'No Aplica',
                            'DependenciaAdministrativa' => 'Servicio de Salud',
                            'TipoPertenenciaEstabGlosa' => 'Perteneciente',
                            'NivelAtencionEstabglosa' => 'Terciario',
                            'TieneServicioUrgencia' => 'Sí',
                            'TipoUrgencia' => 'Urgencia',
                            'ClasificacionTipoSapu' => 'No Aplica',
                            'TipoSistemaSaludGlosa' => 'Público',
                            'EstadoFuncionamiento' => 'Vigente en Operación Habitual',
                            'NivelComplejidadEstabGlosa' => 'Alta',
                            'TipoAtencionEstabGlosa' => 'Abierta',
                        ],
                    ],
                ],
            ]),
        ]);

        $run = app(CenCenterSyncService::class)->sync('15', null);

        $change = CenCenterSyncRunChange::query()
            ->where('cen_center_sync_run_id', $run->id)
            ->where('establecimiento_codigo', '100010')
            ->where('action', 'updated')
            ->first();

        $this->assertNotNull($change);
        $this->assertIsArray($change->context);
        $this->assertArrayHasKey('nombre_oficial', $change->context['changes'] ?? []);
        $this->assertSame('Nombre Antiguo', $change->context['changes']['nombre_oficial']['old'] ?? null);
        $this->assertStringContainsString('Nombre Nuevo', $change->context['changes']['nombre_oficial']['new'] ?? '');
    }

    public function test_sync_soft_deletes_closed_establishment_still_in_api(): void
    {
        Config::set('centinela.national_establishments.enabled', true);

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '15',
            'nombre' => 'SS Test',
            'tipo_dependencia' => 'SERVICIO',
        ]));

        Http::fake([
            '*' => Http::response([
                'success' => true,
                'result' => [
                    'total' => 1,
                    'records' => [
                        [
                            'EstablecimientoCodigo' => '100003',
                            'EstablecimientoGlosa' => 'Hospital Cerrado',
                            'SeremiSaludCodigo_ServicioDeSaludCodigo' => '15',
                            'TipoEstablecimientoGlosa' => 'Hospital',
                            'AmbitoFuncionamiento' => 'Establecimiento de Salud',
                            'Certificacion' => 'No Aplica',
                            'DependenciaAdministrativa' => 'Servicio de Salud',
                            'TipoPertenenciaEstabGlosa' => 'Perteneciente',
                            'NivelAtencionEstabglosa' => 'Terciario',
                            'TieneServicioUrgencia' => 'Sí',
                            'TipoUrgencia' => 'Urgencia',
                            'ClasificacionTipoSapu' => 'No Aplica',
                            'TipoSistemaSaludGlosa' => 'Público',
                            'EstadoFuncionamiento' => 'Cierre Definitivo',
                            'NivelComplejidadEstabGlosa' => 'Alta',
                            'TipoAtencionEstabGlosa' => 'Abierta',
                        ],
                    ],
                ],
            ]),
        ]);

        $run = app(CenCenterSyncService::class)->sync('15', null);

        $center = CenCenter::withTrashed()->where('codigo', '100003')->first();
        $this->assertNotNull($center);
        $this->assertTrue($center->trashed());
        $this->assertSame('Cerrado', $center->estado_funcionamiento?->value);
        $this->assertGreaterThanOrEqual(1, $run->soft_deleted_count);

        $this->assertTrue(
            CenCenterSyncRunIssue::query()
                ->where('cen_center_sync_run_id', $run->id)
                ->where('code', 'closed_establishment_soft_deleted')
                ->exists()
        );
    }

    public function test_sync_imports_rows_when_catalog_ss_codigo_is_zero_padded(): void
    {
        Config::set('centinela.national_establishments.enabled', true);

        $ss = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '5',
            'nombre' => 'Servicio de Salud Valparaíso - San Antonio',
            'tipo_dependencia' => 'SERVICIO',
        ]));

        Http::fake([
            '*' => Http::response([
                'success' => true,
                'result' => [
                    'total' => 1,
                    'records' => [
                        [
                            'EstablecimientoCodigo' => '200723',
                            'EstablecimientoGlosa' => 'Hospital Ovalle Antiguo',
                            'SeremiSaludCodigo_ServicioDeSaludCodigo' => '05',
                            'TipoEstablecimientoGlosa' => 'Hospital',
                            'AmbitoFuncionamiento' => 'Establecimiento de Salud',
                            'Certificacion' => 'No Aplica',
                            'DependenciaAdministrativa' => 'Servicio de Salud',
                            'TipoPertenenciaEstabGlosa' => 'Perteneciente',
                            'NivelAtencionEstabglosa' => 'Secundario',
                            'TieneServicioUrgencia' => 'No',
                            'TipoUrgencia' => 'No Aplica',
                            'ClasificacionTipoSapu' => 'No Aplica',
                            'TipoSistemaSaludGlosa' => 'Público',
                            'EstadoFuncionamiento' => 'Vigente en Operación Habitual',
                            'NivelComplejidadEstabGlosa' => 'Mediana',
                            'TipoAtencionEstabGlosa' => 'Abierta',
                        ],
                    ],
                ],
            ]),
        ]);

        $run = app(CenCenterSyncService::class)->sync(null, null);

        $this->assertSame(0, $run->skipped_count);
        $this->assertSame(1, $run->created_count);
        $this->assertDatabaseHas('cen_centers', [
            'codigo' => '200723',
            'cen_hie_dependency_id' => $ss->id,
            'origen' => CenCenterOrigen::Api->value,
        ]);
        $this->assertDatabaseMissing('cen_center_sync_run_changes', [
            'cen_center_sync_run_id' => $run->id,
            'establecimiento_codigo' => '200723',
            'action' => 'skipped',
        ]);
    }
}
