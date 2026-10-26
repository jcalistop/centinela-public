<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SDD-154: BREAD Voyager para tipos de unidad de Metas (met_unit_types).
 * Incluye data_type, data_rows, permisos (admin + parametrizador) e ítem de
 * menú bajo "Parametrización". Idempotente sobre BD productiva.
 */
return new class extends Migration
{
    private const TABLE_NAME = 'met_unit_types';

    private const BREAD_SLUG = 'unidades-metas';

    private const CONTROLLER = 'App\\Http\\Controllers\\Admin\\MetUnitTypeCrudController';

    private const MENU_NAME = 'admin';

    private const MENU_ROUTE = 'voyager.unidades-metas.index';

    private const MENU_TITLE = 'Unidades (metas)';

    private const MENU_ICON = 'voyager-list';

    /** @var list<string> */
    private const ROLES_WITH_ACCESS = ['admin', 'parametrizador'];

    /** @var list<string> */
    private const PARENT_TITLES = ['Parametrización', 'Configuración'];

    public function up(): void
    {
        if (! Schema::hasTable('data_types') || ! Schema::hasTable('data_rows')) {
            return;
        }

        $dataTypeId = DB::table('data_types')->where('slug', self::BREAD_SLUG)->value('id');

        if ($dataTypeId === null) {
            $dataTypeId = $this->createDataType();
            $this->createDataRows((int) $dataTypeId);
        }

        $this->ensurePermissions();
        $this->assignPermissionsToRoles();
        $this->ensureMenuItem();
    }

    public function down(): void
    {
        if (Schema::hasTable('menu_items')) {
            DB::table('menu_items')->where('route', self::MENU_ROUTE)->delete();
        }

        if (! Schema::hasTable('data_types')) {
            return;
        }

        $dataType = DB::table('data_types')->where('slug', self::BREAD_SLUG)->first();

        if ($dataType !== null && Schema::hasTable('data_rows')) {
            DB::table('data_rows')->where('data_type_id', $dataType->id)->delete();
        }

        $permIds = DB::table('permissions')
            ->where('table_name', self::TABLE_NAME)
            ->pluck('id');

        if ($permIds->isNotEmpty()) {
            if (Schema::hasTable('permission_role')) {
                DB::table('permission_role')->whereIn('permission_id', $permIds)->delete();
            }
            DB::table('permissions')->whereIn('id', $permIds)->delete();
        }

        if ($dataType !== null) {
            DB::table('data_types')->where('id', $dataType->id)->delete();
        }
    }

    private function createDataType(): int
    {
        $now = now();
        $details = json_encode([
            'order_column' => 'orden',
            'order_display_column' => 'nombre',
            'order_direction' => 'asc',
            'default_search_key' => 'codigo',
        ], JSON_THROW_ON_ERROR);

        return (int) DB::table('data_types')->insertGetId([
            'name' => self::TABLE_NAME,
            'slug' => self::BREAD_SLUG,
            'display_name_singular' => 'Unidad (metas)',
            'display_name_plural' => 'Unidades (metas)',
            'icon' => self::MENU_ICON,
            'model_name' => 'App\\Models\\MetUnitType',
            'policy_name' => null,
            'controller' => self::CONTROLLER,
            'description' => null,
            'generate_permissions' => 1,
            'server_side' => 0,
            'details' => $details,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function createDataRows(int $dataTypeId): void
    {
        $enabledDetails = json_encode([
            'on' => 'Sí',
            'off' => 'No',
        ], JSON_THROW_ON_ERROR);

        $fields = [
            ['field' => 'id', 'type' => 'text', 'display_name' => 'Id', 'required' => 1, 'browse' => 0, 'read' => 0, 'edit' => 0, 'add' => 0, 'delete' => 0, 'details' => '{}', 'order' => 1],
            ['field' => 'codigo', 'type' => 'text', 'display_name' => 'Código', 'required' => 1, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1, 'details' => '{}', 'order' => 2],
            ['field' => 'nombre', 'type' => 'text', 'display_name' => 'Nombre', 'required' => 1, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1, 'details' => '{}', 'order' => 3],
            ['field' => 'enabled', 'type' => 'checkbox', 'display_name' => 'Habilitado', 'required' => 0, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 0, 'details' => $enabledDetails, 'order' => 4],
            ['field' => 'orden', 'type' => 'number', 'display_name' => 'Orden', 'required' => 0, 'browse' => 0, 'read' => 1, 'edit' => 0, 'add' => 0, 'delete' => 0, 'details' => '{}', 'order' => 5],
            ['field' => 'created_at', 'type' => 'timestamp', 'display_name' => 'Created At', 'required' => 0, 'browse' => 0, 'read' => 0, 'edit' => 0, 'add' => 0, 'delete' => 0, 'details' => '{}', 'order' => 6],
            ['field' => 'updated_at', 'type' => 'timestamp', 'display_name' => 'Updated At', 'required' => 0, 'browse' => 0, 'read' => 0, 'edit' => 0, 'add' => 0, 'delete' => 0, 'details' => '{}', 'order' => 7],
        ];

        foreach ($fields as $field) {
            DB::table('data_rows')->insert(array_merge($field, ['data_type_id' => $dataTypeId]));
        }
    }

    private function ensurePermissions(): void
    {
        foreach ($this->permissionKeys() as $key) {
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

    private function assignPermissionsToRoles(): void
    {
        if (! Schema::hasTable('permission_role')) {
            return;
        }

        $permIds = DB::table('permissions')
            ->whereIn('key', $this->permissionKeys())
            ->where('table_name', self::TABLE_NAME)
            ->pluck('id');

        if ($permIds->isEmpty()) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('name', self::ROLES_WITH_ACCESS)
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permIds as $permId) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    private function ensureMenuItem(): void
    {
        if (! Schema::hasTable('menus') || ! Schema::hasTable('menu_items') || ! Schema::hasColumn('menu_items', 'route')) {
            return;
        }

        $menuId = DB::table('menus')->where('name', self::MENU_NAME)->value('id');
        if ($menuId === null) {
            return;
        }

        $parentId = $this->resolveParentId((int) $menuId);
        if ($parentId === null) {
            return;
        }

        $existingId = DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->where('route', self::MENU_ROUTE)
            ->value('id');

        if ($existingId !== null) {
            DB::table('menu_items')
                ->where('id', $existingId)
                ->update([
                    'parent_id' => $parentId,
                    'title' => self::MENU_TITLE,
                    'icon_class' => self::MENU_ICON,
                    'updated_at' => now(),
                ]);

            return;
        }

        $maxOrder = (int) DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->where('parent_id', $parentId)
            ->max('order');

        $row = [
            'menu_id' => $menuId,
            'title' => self::MENU_TITLE,
            'url' => '',
            'target' => '_self',
            'icon_class' => self::MENU_ICON,
            'color' => null,
            'parent_id' => $parentId,
            'order' => $maxOrder + 1,
            'route' => self::MENU_ROUTE,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('menu_items', 'parameters')) {
            $row['parameters'] = null;
        }

        DB::table('menu_items')->insert($row);
    }

    private function resolveParentId(int $menuId): ?int
    {
        $viaRoles = DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->where('route', 'voyager.roles.index')
            ->value('parent_id');

        if ($viaRoles !== null) {
            return (int) $viaRoles;
        }

        return DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->whereIn('title', self::PARENT_TITLES)
            ->orderByRaw('CASE WHEN title = ? THEN 0 ELSE 1 END', [self::PARENT_TITLES[0]])
            ->value('id');
    }

    /**
     * @return list<string>
     */
    private function permissionKeys(): array
    {
        return [
            'browse_'.self::TABLE_NAME,
            'read_'.self::TABLE_NAME,
            'edit_'.self::TABLE_NAME,
            'add_'.self::TABLE_NAME,
            'delete_'.self::TABLE_NAME,
        ];
    }
};
