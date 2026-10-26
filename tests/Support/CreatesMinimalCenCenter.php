<?php

namespace Tests\Support;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Models\CenCenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait CreatesMinimalCenCenter
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createMinimalCenCenter(array $overrides = []): CenCenter
    {
        $defaults = $this->defaultCenCenterAttributes();

        if (Schema::hasColumn('cen_centers', 'cen_type_id')) {
            $this->seedLegacyCenCatalogRows();
            $defaults['cen_type_id'] = 1;
            $defaults['cen_scope_id'] = 1;
            $defaults['cen_certification_id'] = 1;
            $defaults['cen_adm_dependency_id'] = 1;
            $defaults['cen_emergency_type_id'] = 1;
            $defaults['cen_provider_type_id'] = 1;
        }

        return CenCenter::unguarded(fn () => CenCenter::create(array_merge($defaults, $overrides)));
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultCenCenterAttributes(): array
    {
        $base = [
            'codigo' => 'TST-'.uniqid(),
            'cen_hie_dependency_id' => 1,
            'cen_type' => 'Centro de Salud',
            'cen_scope' => 'Establecimiento de Salud',
            'nombre_oficial' => 'Centro Test',
            'nombre_corto' => 'CT',
            'cen_certification' => 'No Aplica',
            'cen_adm_dependency' => 'Servicio de Salud',
            'origen' => 'api',
            'cen_emergency_type' => 'No Aplica',
            'cen_provider_type' => 'Público',
        ];

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return array_merge($base, [
                'pertenencia_snss' => 'Perteneciente',
                'nivel_atencion' => 'Primario',
                'tiene_urgencia' => 'No',
                'tipo_sapu' => 'No Aplica',
                'estado_funcionamiento' => CentroEstadoFuncionamiento::VigenteEnOperacionHabitual->value,
                'nivel_complejidad' => 'Baja',
                'tipo_atencion' => 'Abierta',
            ]);
        }

        return array_merge($base, [
            'pertenencia_snss' => 'Perteneciente',
            'nivel_atencion' => 'Primario',
            'tiene_urgencia' => 'No',
            'tipo_sapu' => 'No Aplica',
            'estado_funcionamiento' => CentroEstadoFuncionamiento::VigenteEnOperacionHabitual->value,
            'nivel_complejidad' => 'Baja',
            'tipo_atencion' => 'Abierta',
        ]);
    }

    /**
     * @param  array<string, array{id: int, nombre: string}>  $overrides
     */
    protected function seedLegacyCenCatalogRows(array $overrides = []): void
    {
        $catalogs = [
            'cen_types' => ['id' => 1, 'nombre' => 'Centro de Salud'],
            'cen_scopes' => ['id' => 1, 'nombre' => 'Establecimiento de Salud'],
            'cen_certifications' => ['id' => 1, 'nombre' => 'No Aplica'],
            'cen_adm_dependencies' => ['id' => 1, 'nombre' => 'Servicio de Salud'],
            'cen_emergency_types' => ['id' => 1, 'nombre' => 'No Aplica'],
            'cen_provider_types' => ['id' => 1, 'nombre' => 'Público'],
        ];

        foreach ($overrides as $table => $row) {
            $catalogs[$table] = array_merge($catalogs[$table] ?? ['id' => $row['id'], 'nombre' => $row['nombre']], $row);
        }

        foreach ($catalogs as $table => $row) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)->updateOrInsert(
                ['id' => $row['id']],
                ['nombre' => $row['nombre']],
            );
        }
    }
}
