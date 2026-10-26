<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $legacyCatalogRelationshipFields = [
        'cen_center_belongsto_cen_adm_dependency_relationship',
        'cen_center_belongsto_cen_provider_type_relationship',
        'cen_center_belongsto_cen_type_relationship',
        'cen_center_belongsto_cen_scope_relationship',
        'cen_center_belongsto_cen_certification_relationship',
        'cen_center_belongsto_cen_emergency_type_relationship',
    ];

    /**
     * @var list<string>
     */
    private array $legacyCatalogForeignKeyFields = [
        'cen_type_id',
        'cen_scope_id',
        'cen_adm_dependency_id',
        'cen_certification_id',
        'cen_provider_type_id',
        'cen_emergency_type_id',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('data_types') || ! Schema::hasTable('data_rows')) {
            return;
        }

        $dataTypeId = DB::table('data_types')
            ->where('slug', 'establecimientos')
            ->value('id');

        if (! $dataTypeId) {
            return;
        }

        DB::table('data_rows')
            ->where('data_type_id', $dataTypeId)
            ->whereIn('field', $this->legacyCatalogRelationshipFields)
            ->delete();

        DB::table('data_rows')
            ->where('data_type_id', $dataTypeId)
            ->whereIn('field', $this->legacyCatalogForeignKeyFields)
            ->update([
                'browse' => 0,
                'read' => 0,
                'edit' => 0,
                'add' => 0,
            ]);
    }

    public function down(): void
    {
        // No reversible: los modelos y tablas catálogo ya no existen en el esquema actual.
    }
};
