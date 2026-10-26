<?php

use App\Models\CenHieDependency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BREAD Voyager para Servicios de Salud ({@see CenHieDependency}).
 * Debe ejecutarse antes de la migracion que agrega la relacion hasMany a modulos por servicio.
 */
return new class extends Migration
{
    private const TABLE_NAME = 'cen_hie_dependencies';

    private const BREAD_SLUG = 'servicios-de-salud';

    public function up(): void
    {
        if (! Schema::hasTable('data_types') || ! Schema::hasTable('data_rows')) {
            return;
        }

        if (DB::table('data_types')->where('slug', self::BREAD_SLUG)->exists()) {
            $this->syncExistingBread();

            return;
        }

        $now = now();
        $details = json_encode($this->breadDetails(), JSON_THROW_ON_ERROR);

        $dataTypeId = DB::table('data_types')->insertGetId([
            'name' => self::TABLE_NAME,
            'slug' => self::BREAD_SLUG,
            'display_name_singular' => 'Servicio de Salud',
            'display_name_plural' => 'Servicios de Salud',
            'icon' => 'voyager-company',
            'model_name' => 'App\\Models\\CenHieDependency',
            'policy_name' => null,
            'controller' => null,
            'description' => null,
            'generate_permissions' => 1,
            'server_side' => 0,
            'details' => $details,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $fields = [
            ['field' => 'id', 'type' => 'text', 'display_name' => 'Id', 'required' => 1, 'browse' => 0, 'read' => 0, 'edit' => 1, 'add' => 1, 'delete' => 0, 'details' => '{}', 'order' => 1],
            ['field' => 'codigo', 'type' => 'text', 'display_name' => 'Código', 'required' => 0, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1, 'details' => '{}', 'order' => 2],
            ['field' => 'nombre', 'type' => 'text', 'display_name' => 'Nombre', 'required' => 1, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1, 'details' => '{}', 'order' => 3],
            ['field' => 'tipo_dependencia', 'type' => 'select_dropdown', 'display_name' => 'Tipo dependencia', 'required' => 1, 'browse' => 0, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 0, 'details' => json_encode([
                'default' => 'SERVICIO',
                'options' => [
                    'SERVICIO' => 'Servicio de Salud',
                ],
            ]), 'order' => 4],
            ['field' => 'habilitado', 'type' => 'checkbox', 'display_name' => 'Habilitado', 'required' => 0, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 0, 'details' => json_encode([
                'on' => 'Sí',
                'off' => 'No',
                'view_browse' => 'voyager::servicios-de-salud.partials.browse-habilitado',
                'view_edit' => 'voyager::servicios-de-salud.partials.edit-habilitado',
                'view_add' => 'voyager::servicios-de-salud.partials.edit-habilitado',
            ]), 'order' => 5],
            ['field' => 'created_at', 'type' => 'timestamp', 'display_name' => 'Created At', 'required' => 0, 'browse' => 0, 'read' => 0, 'edit' => 0, 'add' => 0, 'delete' => 0, 'details' => '{}', 'order' => 6],
            ['field' => 'updated_at', 'type' => 'timestamp', 'display_name' => 'Updated At', 'required' => 0, 'browse' => 0, 'read' => 0, 'edit' => 0, 'add' => 0, 'delete' => 0, 'details' => '{}', 'order' => 7],
        ];

        foreach ($fields as $field) {
            DB::table('data_rows')->insert(array_merge($field, ['data_type_id' => $dataTypeId]));
        }

        $this->ensurePermissions();
        $this->assignPermissionsToAdmin();
    }

    public function down(): void
    {
        if (! Schema::hasTable('data_types')) {
            return;
        }

        DB::table('data_rows')
            ->where('field', 'cen_hie_dependency_hasmany_service_module_toggle_relationship')
            ->delete();

        $dataType = DB::table('data_types')->where('slug', self::BREAD_SLUG)->first();
        if (! $dataType) {
            return;
        }

        DB::table('data_rows')->where('data_type_id', $dataType->id)->delete();

        $permIds = DB::table('permissions')
            ->where('table_name', self::TABLE_NAME)
            ->pluck('id');

        if ($permIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $permIds)->delete();
            DB::table('permissions')->whereIn('id', $permIds)->delete();
        }

        DB::table('data_types')->where('id', $dataType->id)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function breadDetails(): array
    {
        return [
            'order_column' => 'codigo',
            'order_display_column' => null,
            'order_direction' => 'asc',
            'default_search_key' => 'codigo',
            'scope' => 'tipoServicio',
        ];
    }

    private function syncExistingBread(): void
    {
        DB::table('data_types')
            ->where('slug', self::BREAD_SLUG)
            ->update([
                'name' => self::TABLE_NAME,
                'display_name_singular' => 'Servicio de Salud',
                'display_name_plural' => 'Servicios de Salud',
                'icon' => 'voyager-company',
                'model_name' => 'App\\Models\\CenHieDependency',
                'details' => json_encode($this->breadDetails(), JSON_THROW_ON_ERROR),
            ]);

        $this->ensurePermissions();
        $this->ensureHabilitadoDataRow();
        $this->assignPermissionsToAdmin();
    }

    private function ensurePermissions(): void
    {
        $keys = [
            'browse_'.self::TABLE_NAME,
            'read_'.self::TABLE_NAME,
            'edit_'.self::TABLE_NAME,
            'add_'.self::TABLE_NAME,
            'delete_'.self::TABLE_NAME,
        ];

        foreach ($keys as $key) {
            $exists = DB::table('permissions')
                ->where('key', $key)
                ->where('table_name', self::TABLE_NAME)
                ->exists();

            if (! $exists) {
                DB::table('permissions')->insert([
                    'key' => $key,
                    'table_name' => self::TABLE_NAME,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function ensureHabilitadoDataRow(): void
    {
        $dataTypeId = DB::table('data_types')->where('slug', self::BREAD_SLUG)->value('id');
        if (! $dataTypeId) {
            return;
        }

        $habilitadoDetails = json_encode([
            'on' => 'Sí',
            'off' => 'No',
            'view_browse' => 'voyager::servicios-de-salud.partials.browse-habilitado',
            'view_edit' => 'voyager::servicios-de-salud.partials.edit-habilitado',
            'view_add' => 'voyager::servicios-de-salud.partials.edit-habilitado',
        ], JSON_THROW_ON_ERROR);

        $row = DB::table('data_rows')
            ->where('data_type_id', $dataTypeId)
            ->where('field', 'habilitado')
            ->first();

        if ($row) {
            $details = json_decode($row->details ?? '{}', true);
            if (! is_array($details)) {
                $details = [];
            }

            unset($details['checked']);
            $details = array_merge($details, json_decode($habilitadoDetails, true, 512, JSON_THROW_ON_ERROR));

            DB::table('data_rows')
                ->where('id', $row->id)
                ->update(['details' => json_encode($details, JSON_THROW_ON_ERROR)]);

            return;
        }

        $maxOrder = (int) DB::table('data_rows')->where('data_type_id', $dataTypeId)->max('order');

        DB::table('data_rows')->insert([
            'data_type_id' => $dataTypeId,
            'field' => 'habilitado',
            'type' => 'checkbox',
            'display_name' => 'Habilitado',
            'required' => 0,
            'browse' => 1,
            'read' => 1,
            'edit' => 1,
            'add' => 1,
            'delete' => 0,
            'details' => $habilitadoDetails,
            'order' => $maxOrder + 1,
        ]);
    }

    private function assignPermissionsToAdmin(): void
    {
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        if (! $adminRoleId) {
            return;
        }

        $permKeys = [
            'browse_'.self::TABLE_NAME,
            'read_'.self::TABLE_NAME,
            'edit_'.self::TABLE_NAME,
            'add_'.self::TABLE_NAME,
            'delete_'.self::TABLE_NAME,
        ];

        foreach ($permKeys as $key) {
            $permId = DB::table('permissions')
                ->where('key', $key)
                ->where('table_name', self::TABLE_NAME)
                ->value('id');

            if (! $permId) {
                continue;
            }

            $exists = DB::table('permission_role')
                ->where('permission_id', $permId)
                ->where('role_id', $adminRoleId)
                ->exists();

            if (! $exists) {
                DB::table('permission_role')->insert([
                    'permission_id' => $permId,
                    'role_id' => $adminRoleId,
                ]);
            }
        }
    }
};
