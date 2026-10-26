<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Voyager: BREAD modulos-por-servicio, relacion hasMany, menu Parametrizacion y orden del submenu.
 */
return new class extends Migration
{
    private const BREAD_NAME = 'service_module_toggles';

    private const BREAD_SLUG = 'modulos-por-servicio';

    private const LEGACY_SLUG = 'service-module-toggles';

    private const PARENT_SLUG = 'servicios-de-salud';

    private const MENU_NAME = 'admin';

    private const PARENT_TITLE = 'Parametrización';

    private const LEGACY_PARENT_TITLE = 'Configuración';

    private const TOOLS_TITLE = 'Herramientas';

    private const TOP_LEVEL_PARAMETRIZACION_ORDER = 3;

    private const TOP_LEVEL_TOOLS_ORDER = 4;

    /** @var array<string, int> */
    private const PARAMETRIZACION_ORDER = [
        'voyager.roles.index' => 1,
        'voyager.instrumentos-evaluacion.index' => 2,
        'voyager.servicios-de-salud.index' => 3,
        'voyager.modulos-por-servicio.index' => 4,
        'voyager.year.index' => 5,
        'voyager.cortes.index' => 6,
    ];

    /** @var array<string, array{title: string, icon: string}> */
    private const MENU_DEFINITIONS = [
        'voyager.servicios-de-salud.index' => [
            'title' => 'Servicios de Salud',
            'icon' => 'voyager-company',
        ],
        'voyager.modulos-por-servicio.index' => [
            'title' => 'Módulos por servicio',
            'icon' => 'voyager-tools',
        ],
    ];

    public function up(): void
    {
        $this->ensurePermissions();
        $this->ensureChildBread();
        $this->normalizeLegacyBreadSlug();
        $this->assignPermissionsToAdmin();
        $this->ensureParentHasManyRelationship();
        $this->syncParametrizacionMenu();
    }

    public function down(): void
    {
        $this->rollbackParametrizacionMenu();

        $menuId = DB::table('menus')->where('name', self::MENU_NAME)->value('id');
        if ($menuId) {
            DB::table('menu_items')
                ->where('menu_id', $menuId)
                ->where('route', 'voyager.'.self::BREAD_SLUG.'.index')
                ->delete();
        }

        DB::table('data_rows')
            ->where('field', 'cen_hie_dependency_hasmany_service_module_toggle_relationship')
            ->delete();

        $dataType = DB::table('data_types')->where('name', self::BREAD_NAME)->first();
        if ($dataType) {
            DB::table('data_rows')->where('data_type_id', $dataType->id)->delete();
            DB::table('data_types')->where('id', $dataType->id)->delete();
        }

        $permIds = DB::table('permissions')
            ->where('table_name', self::BREAD_NAME)
            ->pluck('id');

        if ($permIds->isNotEmpty()) {
            DB::table('permission_role')
                ->whereIn('permission_id', $permIds)
                ->delete();

            DB::table('permissions')
                ->whereIn('id', $permIds)
                ->delete();
        }
    }

    private function ensurePermissions(): void
    {
        $keys = [
            'browse_'.self::BREAD_NAME,
            'read_'.self::BREAD_NAME,
            'edit_'.self::BREAD_NAME,
            'add_'.self::BREAD_NAME,
            'delete_'.self::BREAD_NAME,
        ];

        foreach ($keys as $key) {
            $exists = DB::table('permissions')
                ->where('key', $key)
                ->where('table_name', self::BREAD_NAME)
                ->exists();

            if (! $exists) {
                DB::table('permissions')->insert([
                    'key' => $key,
                    'table_name' => self::BREAD_NAME,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function ensureChildBread(): void
    {
        if (DB::table('data_types')->where('name', self::BREAD_NAME)->exists()) {
            return;
        }

        $now = now();
        $details = json_encode([
            'order_column' => 'module_key',
            'order_display_column' => null,
            'order_direction' => 'asc',
            'default_search_key' => null,
        ], JSON_THROW_ON_ERROR);

        $dataTypeId = DB::table('data_types')->insertGetId([
            'name' => self::BREAD_NAME,
            'slug' => self::BREAD_SLUG,
            'display_name_singular' => 'Módulo por servicio',
            'display_name_plural' => 'Módulos por servicio',
            'icon' => 'voyager-tools',
            'model_name' => 'App\\Models\\ServiceModuleToggle',
            'policy_name' => null,
            'controller' => 'App\\Http\\Controllers\\Voyager\\ServiceModuleTogglesController',
            'description' => null,
            'generate_permissions' => 1,
            'server_side' => 0,
            'details' => $details,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $fields = [
            ['field' => 'id', 'type' => 'text', 'display_name' => 'Id', 'required' => 1, 'browse' => 0, 'read' => 0, 'edit' => 1, 'add' => 1, 'delete' => 0, 'details' => '{}', 'order' => 1],
            ['field' => 'cen_hie_dependency_id', 'type' => 'text', 'display_name' => 'Servicio de Salud', 'required' => 1, 'browse' => 0, 'read' => 1, 'edit' => 1, 'add' => 0, 'delete' => 0, 'details' => '{}', 'order' => 2],
            ['field' => 'module_key', 'type' => 'select_dropdown', 'display_name' => 'Modulo', 'required' => 1, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1, 'details' => json_encode([
                'display' => ['width' => '6'],
                'default' => '',
                'options' => config('modules.modules'),
            ]), 'order' => 3],
            ['field' => 'enabled', 'type' => 'checkbox', 'display_name' => 'Habilitado', 'required' => 1, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1, 'details' => json_encode([
                'display' => ['width' => '6'],
                'on' => 'Si',
                'off' => 'No',
                'checked' => true,
            ]), 'order' => 4],
            ['field' => 'created_at', 'type' => 'timestamp', 'display_name' => 'Created At', 'required' => 0, 'browse' => 0, 'read' => 0, 'edit' => 0, 'add' => 0, 'delete' => 0, 'details' => '{}', 'order' => 5],
            ['field' => 'updated_at', 'type' => 'timestamp', 'display_name' => 'Updated At', 'required' => 0, 'browse' => 0, 'read' => 0, 'edit' => 0, 'add' => 0, 'delete' => 0, 'details' => '{}', 'order' => 6],
        ];

        foreach ($fields as $field) {
            DB::table('data_rows')->insert(array_merge($field, ['data_type_id' => $dataTypeId]));
        }
    }

    private function normalizeLegacyBreadSlug(): void
    {
        DB::table('data_types')
            ->where('slug', self::LEGACY_SLUG)
            ->where('name', self::BREAD_NAME)
            ->update([
                'slug' => self::BREAD_SLUG,
                'display_name_singular' => 'Módulo por servicio',
                'display_name_plural' => 'Módulos por servicio',
                'icon' => 'voyager-tools',
            ]);
    }

    private function assignPermissionsToAdmin(): void
    {
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        if (! $adminRoleId) {
            return;
        }

        $permKeys = [
            'browse_'.self::BREAD_NAME,
            'read_'.self::BREAD_NAME,
            'edit_'.self::BREAD_NAME,
            'add_'.self::BREAD_NAME,
            'delete_'.self::BREAD_NAME,
        ];

        $permIds = DB::table('permissions')
            ->whereIn('key', $permKeys)
            ->where('table_name', self::BREAD_NAME)
            ->pluck('id');

        foreach ($permIds as $permId) {
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

    private function ensureParentHasManyRelationship(): void
    {
        $dataTypeId = DB::table('data_types')
            ->where('slug', self::PARENT_SLUG)
            ->value('id');

        if (! $dataTypeId) {
            return;
        }

        $existing = DB::table('data_rows')
            ->where('data_type_id', $dataTypeId)
            ->where('field', 'cen_hie_dependency_hasmany_service_module_toggle_relationship')
            ->exists();

        if ($existing) {
            $this->syncRelationshipRowDetails($dataTypeId);

            return;
        }

        $maxOrder = DB::table('data_rows')
            ->where('data_type_id', $dataTypeId)
            ->max('order');

        DB::table('data_rows')->insert([
            'data_type_id' => $dataTypeId,
            'field' => 'cen_hie_dependency_hasmany_service_module_toggle_relationship',
            'type' => 'relationship',
            'display_name' => 'Módulos por servicio',
            'required' => 0,
            'browse' => 1,
            'read' => 1,
            'edit' => 1,
            'add' => 1,
            'delete' => 0,
            'details' => json_encode($this->relationshipDetails()),
            'order' => $maxOrder + 1,
        ]);
    }

    private function syncRelationshipRowDetails(int $dataTypeId): void
    {
        $row = DB::table('data_rows')
            ->where('data_type_id', $dataTypeId)
            ->where('field', 'cen_hie_dependency_hasmany_service_module_toggle_relationship')
            ->first();

        if (! $row) {
            return;
        }

        $details = json_decode($row->details ?? '{}', true);
        if (! is_array($details)) {
            $details = [];
        }

        $details = array_merge($details, $this->relationshipDetails());

        DB::table('data_rows')
            ->where('id', $row->id)
            ->update([
                'display_name' => 'Módulos por servicio',
                'details' => json_encode($details, JSON_THROW_ON_ERROR),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function relationshipDetails(): array
    {
        return [
            'model' => 'App\\Models\\ServiceModuleToggle',
            'table' => 'service_module_toggles',
            'type' => 'hasMany',
            'column' => 'cen_hie_dependency_id',
            'key' => 'id',
            'label' => 'module_key',
            'scope' => 'enabled',
            'view_browse' => 'voyager::servicios-de-salud.partials.browse-enabled-modules',
            'pivot_table' => 'service_module_toggles',
            'pivot' => '0',
            'taggable' => '0',
        ];
    }

    private function syncParametrizacionMenu(): void
    {
        if (! Schema::hasTable('menus') || ! Schema::hasTable('menu_items')) {
            return;
        }

        if (! Schema::hasColumn('menu_items', 'route')) {
            return;
        }

        $menuId = DB::table('menus')->where('name', self::MENU_NAME)->value('id');
        $parentId = $this->resolveParametrizacionParentId($menuId);

        if (! $menuId || ! $parentId) {
            return;
        }

        $this->normalizeParentTitle($menuId, $parentId);
        $this->reorderTopLevelBeforeTools($menuId, $parentId);

        foreach (self::MENU_DEFINITIONS as $route => $definition) {
            $this->ensureChildMenuItem($menuId, $parentId, $route, $definition['title'], $definition['icon']);
        }

        foreach (self::PARAMETRIZACION_ORDER as $route => $order) {
            DB::table('menu_items')
                ->where('menu_id', $menuId)
                ->where('parent_id', $parentId)
                ->where('route', $route)
                ->update([
                    'order' => $order,
                    'updated_at' => now(),
                ]);
        }

        $this->reorderRemainingMenuChildren($menuId, $parentId);
    }

    private function rollbackParametrizacionMenu(): void
    {
        if (! Schema::hasTable('menu_items') || ! Schema::hasColumn('menu_items', 'route')) {
            return;
        }

        $menuId = DB::table('menus')->where('name', self::MENU_NAME)->value('id');
        $parentId = $this->resolveParametrizacionParentId($menuId);

        if (! $menuId || ! $parentId) {
            return;
        }

        foreach (array_keys(self::MENU_DEFINITIONS) as $route) {
            DB::table('menu_items')
                ->where('menu_id', $menuId)
                ->where('parent_id', $parentId)
                ->where('route', $route)
                ->delete();
        }

        $previousOrder = [
            'voyager.roles.index' => 1,
            'voyager.instrumentos-evaluacion.index' => 2,
            'voyager.year.index' => 3,
            'voyager.cortes.index' => 4,
            'voyager.instrumentos-locales.index' => 6,
            'voyager.pestanas-instrumentos.index' => 7,
        ];

        foreach ($previousOrder as $route => $order) {
            DB::table('menu_items')
                ->where('menu_id', $menuId)
                ->where('parent_id', $parentId)
                ->where('route', $route)
                ->update([
                    'order' => $order,
                    'updated_at' => now(),
                ]);
        }

        $sharedOrder = self::TOP_LEVEL_PARAMETRIZACION_ORDER;

        DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->where('id', $parentId)
            ->whereNull('parent_id')
            ->update([
                'order' => $sharedOrder,
                'updated_at' => now(),
            ]);

        DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->whereNull('parent_id')
            ->where('title', self::TOOLS_TITLE)
            ->update([
                'order' => $sharedOrder,
                'updated_at' => now(),
            ]);

        DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->where('id', $parentId)
            ->where('title', self::PARENT_TITLE)
            ->update([
                'title' => self::LEGACY_PARENT_TITLE,
                'updated_at' => now(),
            ]);
    }

    private function ensureChildMenuItem(
        int $menuId,
        int $parentId,
        string $route,
        string $title,
        string $icon
    ): void {
        $exists = DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->where('route', $route)
            ->exists();

        if ($exists) {
            DB::table('menu_items')
                ->where('menu_id', $menuId)
                ->where('route', $route)
                ->update([
                    'parent_id' => $parentId,
                    'title' => $title,
                    'icon_class' => $icon,
                    'updated_at' => now(),
                ]);

            return;
        }

        $row = [
            'menu_id' => $menuId,
            'title' => $title,
            'url' => '',
            'target' => '_self',
            'icon_class' => $icon,
            'color' => '',
            'parent_id' => $parentId,
            'order' => self::PARAMETRIZACION_ORDER[$route] ?? 99,
            'route' => $route,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('menu_items', 'parameters')) {
            $row['parameters'] = '';
        }

        DB::table('menu_items')->insert($row);
    }

    private function resolveParametrizacionParentId(?int $menuId): ?int
    {
        if (! $menuId) {
            return null;
        }

        $parentId = DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->where('route', 'voyager.roles.index')
            ->value('parent_id');

        if ($parentId) {
            return (int) $parentId;
        }

        $byTitle = DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->whereIn('title', [self::PARENT_TITLE, self::LEGACY_PARENT_TITLE])
            ->orderByRaw('CASE WHEN title = ? THEN 0 ELSE 1 END', [self::PARENT_TITLE])
            ->value('id');

        return $byTitle ? (int) $byTitle : null;
    }

    private function normalizeParentTitle(int $menuId, int $parentId): void
    {
        DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->where('id', $parentId)
            ->where('title', self::LEGACY_PARENT_TITLE)
            ->update([
                'title' => self::PARENT_TITLE,
                'updated_at' => now(),
            ]);
    }

    private function reorderTopLevelBeforeTools(int $menuId, int $parentId): void
    {
        DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->where('id', $parentId)
            ->whereNull('parent_id')
            ->update([
                'order' => self::TOP_LEVEL_PARAMETRIZACION_ORDER,
                'updated_at' => now(),
            ]);

        DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->whereNull('parent_id')
            ->where('title', self::TOOLS_TITLE)
            ->update([
                'order' => self::TOP_LEVEL_TOOLS_ORDER,
                'updated_at' => now(),
            ]);
    }

    private function reorderRemainingMenuChildren(int $menuId, int $parentId): void
    {
        $managedRoutes = array_keys(self::PARAMETRIZACION_ORDER);

        $others = DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->where('parent_id', $parentId)
            ->whereNotIn('route', $managedRoutes)
            ->orderBy('order')
            ->orderBy('id')
            ->pluck('id');

        $nextOrder = max(self::PARAMETRIZACION_ORDER) + 1;

        foreach ($others as $itemId) {
            DB::table('menu_items')
                ->where('id', $itemId)
                ->update([
                    'order' => $nextOrder,
                    'updated_at' => now(),
                ]);
            $nextOrder++;
        }
    }
};
