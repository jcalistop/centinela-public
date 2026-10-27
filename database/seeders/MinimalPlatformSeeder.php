<?php

namespace Database\Seeders;

use App\Enums\Cen\DependenciaTipo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use TCG\Voyager\Models\Permission;

/**
 * Bootstrap mínimo F1b (SDD-220): roles invariantes, dependencia placeholder,
 * año default, BREAD core (users/roles/menus/years), menús Voyager y settings —
 * sin datos operativos SS.
 *
 * Tras UsableCatalogSeeder (SDD-240): no reinyectar menú Voyager default (Media,
 * Configuración, Ajustes duplicados); solo reconciliar drift y completar gaps.
 */
class MinimalPlatformSeeder extends Seeder
{
    /**
     * @var list<array{id: int, name: string, display_name: string}>
     */
    private const ROLES = [
        ['id' => 1, 'name' => 'admin', 'display_name' => 'Administrador'],
        ['id' => 2, 'name' => 'control-gestion', 'display_name' => 'Control de Gestión'],
        ['id' => 3, 'name' => 'visualizador', 'display_name' => 'Visualizador'],
        ['id' => 4, 'name' => 'referente', 'display_name' => 'Referente'],
        ['id' => 5, 'name' => 'parametrizador', 'display_name' => 'Parametrizador'],
        ['id' => 6, 'name' => 'agente', 'display_name' => 'Agente'],
    ];

    public function run(): void
    {
        $this->seedRoles();
        $this->seedPlaceholderDependency();
        $this->seedDefaultYear();
        $this->seedBrowseAdminPermission();
        $this->seedPublicInfoPanelsPermission();
        $this->seedCoreBreads();
        $this->seedAdminMenu();
        $this->seedMinimalSettings();
    }

    private function seedRoles(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $now = now();

        foreach (self::ROLES as $role) {
            DB::table('roles')->updateOrInsert(
                ['id' => $role['id']],
                [
                    'name' => $role['name'],
                    'display_name' => $role['display_name'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function seedPlaceholderDependency(): void
    {
        if (! Schema::hasTable('cen_hie_dependencies')) {
            return;
        }

        // Con catálogo CEN (SDD-240) no pisar filas reales (id 1 puede ser «No Aplica» de prod).
        if (DB::table('cen_hie_dependencies')->exists()) {
            return;
        }

        $now = now();

        DB::table('cen_hie_dependencies')->insert([
            'id' => 1,
            'codigo' => null,
            'nombre' => 'No Aplica',
            'tipo_dependencia' => DependenciaTipo::Otro->value,
            'habilitado' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Año activo mínimo: EnsureIndicatorYearIsSet / Year::getDefaultYear() usan
     * where('default', 1)->firstOrFail() en /admin (404 si no hay fila).
     */
    private function seedDefaultYear(): void
    {
        if (! Schema::hasTable('years')) {
            return;
        }

        $now = now();
        $ano = (int) $now->year;

        DB::table('years')->where('default', true)->update(['default' => false]);

        $existingId = DB::table('years')->where('ano', $ano)->value('id');

        if ($existingId !== null) {
            DB::table('years')->where('id', $existingId)->update([
                'default' => true,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('years')->insert([
                'ano' => $ano,
                'default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Cache::forget('default_year');
    }

    private function seedBrowseAdminPermission(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $now = now();

        $permissionId = DB::table('permissions')->where('key', 'browse_admin')->value('id');

        if ($permissionId === null) {
            $permissionId = DB::table('permissions')->insertGetId([
                'key' => 'browse_admin',
                'table_name' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $exists = DB::table('permission_role')
            ->where('permission_id', $permissionId)
            ->where('role_id', 1)
            ->exists();

        if (! $exists) {
            DB::table('permission_role')->insert([
                'permission_id' => $permissionId,
                'role_id' => 1,
            ]);
        }
    }

    /**
     * SDD-282 / SDD-306: curaduría Voyager `/admin/paneles-publicos`.
     * El fixture usable-catalog (export 2026-10-06) no incluye ítem ni permiso;
     * db:seed borra menu_items y dejaría el gap de la migración.
     */
    private function seedPublicInfoPanelsPermission(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $now = now();
        $permissionId = DB::table('permissions')->where('key', 'browse_paneles-publicos')->value('id');

        if ($permissionId === null) {
            $permissionId = DB::table('permissions')->insertGetId([
                'key' => 'browse_paneles-publicos',
                'table_name' => 'paneles-publicos',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! Schema::hasTable('permission_role')) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('name', ['admin', 'parametrizador'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            $exists = DB::table('permission_role')
                ->where('permission_id', $permissionId)
                ->where('role_id', (int) $roleId)
                ->exists();

            if (! $exists) {
                DB::table('permission_role')->insert([
                    'permission_id' => (int) $permissionId,
                    'role_id' => (int) $roleId,
                ]);
            }
        }
    }

    private function ensurePublicInfoPanelsMenuItem(
        int $adminMenuId,
        Carbon $now,
        bool $hasRoute,
        bool $hasParameters,
    ): void {
        if (! $hasRoute) {
            return;
        }

        $parentId = $this->resolvePublicInfoPanelsParentId($adminMenuId);
        if ($parentId === null) {
            return;
        }

        $existingId = DB::table('menu_items')
            ->where('menu_id', $adminMenuId)
            ->where('route', 'centinela.admin.paneles-publicos')
            ->value('id');

        if ($existingId !== null) {
            DB::table('menu_items')
                ->where('id', $existingId)
                ->update([
                    'parent_id' => $parentId,
                    'title' => 'Paneles públicos',
                    'icon_class' => 'voyager-news',
                    'updated_at' => $now,
                ]);

            return;
        }

        $maxOrder = (int) DB::table('menu_items')
            ->where('menu_id', $adminMenuId)
            ->where('parent_id', $parentId)
            ->max('order');

        $row = [
            'menu_id' => $adminMenuId,
            'title' => 'Paneles públicos',
            'url' => '',
            'target' => '_self',
            'icon_class' => 'voyager-news',
            'color' => null,
            'parent_id' => $parentId,
            'order' => $maxOrder + 1,
            'route' => 'centinela.admin.paneles-publicos',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($hasParameters) {
            $row['parameters'] = null;
        }

        DB::table('menu_items')->insert($row);
    }

    private function resolvePublicInfoPanelsParentId(int $adminMenuId): ?int
    {
        $viaRoles = DB::table('menu_items')
            ->where('menu_id', $adminMenuId)
            ->where('route', 'voyager.roles.index')
            ->value('parent_id');

        if ($viaRoles !== null) {
            return (int) $viaRoles;
        }

        $id = DB::table('menu_items')
            ->where('menu_id', $adminMenuId)
            ->whereIn('title', ['Parametrización', 'Configuración'])
            ->orderByRaw('CASE WHEN title = ? THEN 0 ELSE 1 END', ['Parametrización'])
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * BREAD mínimo para que Voyager registre rutas (voyager.users.index, etc.)
     * y el sidebar pueda enlazar Usuarios / Roles / Menús / Años.
     */
    private function seedCoreBreads(): void
    {
        if (! Schema::hasTable('data_types')) {
            return;
        }

        $now = now();

        $breads = [
            [
                'name' => 'users',
                'slug' => 'users',
                'display_name_singular' => 'Usuario',
                'display_name_plural' => 'Usuarios',
                'icon' => 'voyager-person',
                'model_name' => 'App\\Models\\User',
                'policy_name' => 'TCG\\Voyager\\Policies\\UserPolicy',
                'controller' => '\\App\\Http\\Controllers\\Voyager\\CentinelaVoyagerUserController',
                'details' => '{"order_column":null,"order_display_column":null,"order_direction":"desc","default_search_key":null,"scope":"voyagerActorManageable"}',
            ],
            [
                'name' => 'roles',
                'slug' => 'roles',
                'display_name_singular' => 'Rol',
                'display_name_plural' => 'Roles',
                'icon' => 'voyager-lock',
                'model_name' => 'App\\Models\\Role',
                'policy_name' => null,
                'controller' => 'TCG\\Voyager\\Http\\Controllers\\VoyagerRoleController',
                'details' => '{"order_column":null,"order_display_column":null,"order_direction":"desc","default_search_key":null,"scope":null}',
            ],
            [
                'name' => 'menus',
                'slug' => 'menus',
                'display_name_singular' => 'Menú',
                'display_name_plural' => 'Menús',
                'icon' => 'voyager-list',
                'model_name' => 'TCG\\Voyager\\Models\\Menu',
                'policy_name' => null,
                'controller' => '',
                'details' => null,
            ],
            [
                'name' => 'years',
                'slug' => 'year',
                'display_name_singular' => 'Año',
                'display_name_plural' => 'Años',
                'icon' => 'voyager-calendar',
                'model_name' => 'App\\Models\\Year',
                'policy_name' => null,
                'controller' => null,
                'details' => '{"order_column":null,"order_display_column":null,"order_direction":"asc","default_search_key":null,"scope":null}',
            ],
        ];

        foreach ($breads as $bread) {
            DB::table('data_types')->updateOrInsert(
                ['slug' => $bread['slug']],
                [
                    'name' => $bread['name'],
                    'display_name_singular' => $bread['display_name_singular'],
                    'display_name_plural' => $bread['display_name_plural'],
                    'icon' => $bread['icon'],
                    'model_name' => $bread['model_name'],
                    'policy_name' => $bread['policy_name'],
                    'controller' => $bread['controller'],
                    'description' => null,
                    'generate_permissions' => 1,
                    'server_side' => 0,
                    'details' => $bread['details'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            Permission::generateFor($bread['name']);
        }

        if (! Schema::hasTable('permission_role')) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('table_name', ['users', 'roles', 'menus', 'years'])
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            $exists = DB::table('permission_role')
                ->where('permission_id', $permissionId)
                ->where('role_id', 1)
                ->exists();

            if (! $exists) {
                DB::table('permission_role')->insert([
                    'permission_id' => $permissionId,
                    'role_id' => 1,
                ]);
            }
        }
    }

    private function seedAdminMenu(): void
    {
        if (! Schema::hasTable('menus') || ! Schema::hasTable('menu_items')) {
            return;
        }

        $now = now();
        $hasRoute = Schema::hasColumn('menu_items', 'route');
        $hasParameters = Schema::hasColumn('menu_items', 'parameters');

        $adminMenuId = $this->ensureMenu('admin', $now);
        $inicioMenuId = $this->ensureMenu('inicio', $now);

        // Menú de producto (ref_prod / UsableCatalog): no añadir defaults Voyager.
        if ($this->hasCatalogAdminMenu($adminMenuId)) {
            $this->reconcileCatalogAdminMenu($adminMenuId, $now);
            $this->ensurePublicInfoPanelsMenuItem($adminMenuId, $now, $hasRoute, $hasParameters);
            Cache::forget('voyager_menu_admin');
            Cache::forget('voyager_menu_inicio');

            return;
        }

        // Sidebar: menu('inicio') + menu('admin', '_json') — ver resources/views/vendor/voyager/dashboard/sidebar.blade.php
        $this->upsertMenuItem($inicioMenuId, null, [
            'title' => 'Inicio',
            'icon_class' => 'voyager-home',
            'order' => 1,
            'route' => 'voyager.dashboard',
        ], $now, $hasRoute, $hasParameters);

        $this->upsertMenuItem($adminMenuId, null, [
            'title' => 'Usuarios',
            'icon_class' => 'voyager-person',
            'order' => 1,
            'route' => 'voyager.users.index',
        ], $now, $hasRoute, $hasParameters);

        $configId = $this->upsertMenuItem($adminMenuId, null, [
            'title' => 'Configuración',
            'icon_class' => 'voyager-params',
            'order' => 2,
            'route' => null,
        ], $now, $hasRoute, $hasParameters);

        $this->upsertMenuItem($adminMenuId, $configId, [
            'title' => 'Roles',
            'icon_class' => 'voyager-lock',
            'order' => 1,
            'route' => 'voyager.roles.index',
        ], $now, $hasRoute, $hasParameters);

        $this->upsertMenuItem($adminMenuId, $configId, [
            'title' => 'Años',
            'icon_class' => 'voyager-calendar',
            'order' => 2,
            'route' => 'voyager.year.index',
        ], $now, $hasRoute, $hasParameters);

        $toolsId = $this->upsertMenuItem($adminMenuId, null, [
            'title' => 'Herramientas',
            'icon_class' => 'voyager-terminal',
            'order' => 3,
            'route' => null,
        ], $now, $hasRoute, $hasParameters);

        $this->upsertMenuItem($adminMenuId, $toolsId, [
            'title' => 'Media',
            'icon_class' => 'voyager-images',
            'order' => 1,
            'route' => 'voyager.media.index',
        ], $now, $hasRoute, $hasParameters);

        $this->upsertMenuItem($adminMenuId, $toolsId, [
            'title' => 'Diseñador de Menús',
            'icon_class' => 'voyager-list',
            'order' => 2,
            'route' => 'voyager.menus.index',
        ], $now, $hasRoute, $hasParameters);

        $this->upsertMenuItem($adminMenuId, $toolsId, [
            'title' => 'Base de Datos',
            'icon_class' => 'voyager-data',
            'order' => 3,
            'route' => 'voyager.database.index',
        ], $now, $hasRoute, $hasParameters);

        $this->upsertMenuItem($adminMenuId, $toolsId, [
            'title' => 'BREAD',
            'icon_class' => 'voyager-bread',
            'order' => 4,
            'route' => 'voyager.bread.index',
        ], $now, $hasRoute, $hasParameters);

        $this->upsertMenuItem($adminMenuId, $toolsId, [
            'title' => 'Ajustes del sitio',
            'icon_class' => 'voyager-settings',
            'order' => 5,
            'route' => 'voyager.settings.index',
        ], $now, $hasRoute, $hasParameters);

        $aboutId = $this->upsertMenuItem($adminMenuId, null, [
            'title' => 'Acerca de Centinela',
            'icon_class' => 'voyager-info-circled',
            'order' => 5,
            'route' => null,
        ], $now, $hasRoute, $hasParameters);

        $this->upsertMenuItem($adminMenuId, $aboutId, [
            'title' => 'Qué es Centinela',
            'icon_class' => 'voyager-question',
            'order' => 1,
            'route' => 'acerca-de.index',
        ], $now, $hasRoute, $hasParameters);

        $this->upsertMenuItem($adminMenuId, $aboutId, [
            'title' => 'Versiones',
            'icon_class' => 'voyager-code',
            'order' => 2,
            'route' => 'acerca-de.releases',
        ], $now, $hasRoute, $hasParameters);

        $this->ensurePublicInfoPanelsMenuItem($adminMenuId, $now, $hasRoute, $hasParameters);

        // Renombre histórico del ítem de settings bajo Herramientas.
        if ($hasRoute) {
            DB::table('menu_items')
                ->where('menu_id', $adminMenuId)
                ->where('route', 'voyager.settings.index')
                ->where('title', 'Configuración')
                ->update(['title' => 'Ajustes del sitio', 'updated_at' => $now]);
        }

        Cache::forget('voyager_menu_admin');
        Cache::forget('voyager_menu_inicio');
    }

    private function hasCatalogAdminMenu(int $adminMenuId): bool
    {
        return DB::table('menu_items')
            ->where('menu_id', $adminMenuId)
            ->whereNull('parent_id')
            ->where('title', 'Parametrización')
            ->exists();
    }

    /**
     * Quita drift de MinimalPlatform/Voyager sobre el menú exportado de prod:
     * Media, grupo Configuración, Ajustes duplicados; orden Parametrización antes que Herramientas.
     */
    private function reconcileCatalogAdminMenu(int $adminMenuId, Carbon $now): void
    {
        // Media no está en menú de producción.
        DB::table('menu_items')
            ->where('menu_id', $adminMenuId)
            ->where('route', 'voyager.media.index')
            ->delete();

        // Grupo top-level "Configuración" (Roles/Años duplicados) — no existe en prod.
        $configParentIds = DB::table('menu_items')
            ->where('menu_id', $adminMenuId)
            ->whereNull('parent_id')
            ->where('title', 'Configuración')
            ->where(function ($q): void {
                $q->whereNull('route')->orWhere('route', '');
            })
            ->pluck('id');

        foreach ($configParentIds as $parentId) {
            DB::table('menu_items')->where('parent_id', $parentId)->delete();
            DB::table('menu_items')->where('id', $parentId)->delete();
        }

        // Un solo ítem settings bajo Herramientas; título canónico de prod = Configuración.
        $settingsItems = DB::table('menu_items')
            ->where('menu_id', $adminMenuId)
            ->where('route', 'voyager.settings.index')
            ->orderBy('id')
            ->get(['id', 'parent_id', 'title']);

        if ($settingsItems->isNotEmpty()) {
            $keep = $settingsItems->first(
                fn ($item): bool => $item->parent_id !== null
            ) ?? $settingsItems->first();

            foreach ($settingsItems as $item) {
                if ((int) $item->id === (int) $keep->id) {
                    continue;
                }
                DB::table('menu_items')->where('id', $item->id)->delete();
            }

            // Producción (ref_prod): settings bajo Herramientas se llama «Configuración».
            // El usuario reportó que «Ajustes del Sitio» no debe aparecer; si tampoco
            // quieren el ítem settings, se puede eliminar en una enmienda. Mientras:
            // alinear título a ref_prod. Si hay 0 deseados en UI, prod puede ocultarlo
            // por permiso — aquí dejamos 1 fila canónica.
            $herramientasId = DB::table('menu_items')
                ->where('menu_id', $adminMenuId)
                ->whereNull('parent_id')
                ->where('title', 'Herramientas')
                ->value('id');

            DB::table('menu_items')->where('id', $keep->id)->update([
                'title' => 'Configuración',
                'parent_id' => $herramientasId ?? $keep->parent_id,
                'order' => 5,
                'updated_at' => $now,
            ]);
        }

        DB::table('menu_items')
            ->where('menu_id', $adminMenuId)
            ->whereNull('parent_id')
            ->where('title', 'Parametrización')
            ->update(['order' => 3, 'updated_at' => $now]);

        DB::table('menu_items')
            ->where('menu_id', $adminMenuId)
            ->whereNull('parent_id')
            ->where('title', 'Herramientas')
            ->update(['order' => 4, 'updated_at' => $now]);

        // Orden canónico ref_prod: Soporte (4) antes que Acerca de Centinela (5).
        DB::table('menu_items')
            ->where('menu_id', $adminMenuId)
            ->whereNull('parent_id')
            ->where('title', 'Soporte')
            ->update(['order' => 4, 'updated_at' => $now]);

        DB::table('menu_items')
            ->where('menu_id', $adminMenuId)
            ->whereNull('parent_id')
            ->where('title', 'Acerca de Centinela')
            ->update(['order' => 5, 'updated_at' => $now]);

        // Íconos canónicos de producción (ref_prod); MinimalPlatform no debe pisarlos.
        foreach ($this->catalogMenuIconClasses() as $title => $iconClass) {
            DB::table('menu_items')
                ->where('menu_id', $adminMenuId)
                ->where('title', $title)
                ->update(['icon_class' => $iconClass, 'updated_at' => $now]);
        }
    }

    /**
     * @return array<string, string> title => icon_class (catálogo de referencia)
     */
    private function catalogMenuIconClasses(): array
    {
        return [
            'Usuarios' => 'voyager-person',
            'Roles' => 'voyager-lock',
            'Diseñador de Menús' => 'voyager-list',
            'Visualizador' => 'voyager-eye',
            'Establecimientos' => 'voyager-shop',
            'Que es Centinela' => 'voyager-question',
            'Qué es Centinela' => 'voyager-question',
            'Base de Datos' => 'voyager-data',
            'Versiones' => 'voyager-code',
            'Instr. de evaluación' => 'voyager-categories',
            'BREAD' => 'voyager-bread',
            'Parametrización' => 'voyager-params',
            'Direcciones' => 'voyager-compass',
            'Servicios de Salud' => 'voyager-company',
            'Herramientas' => 'voyager-terminal',
            'Compás' => 'voyager-compass',
            'Soporte' => 'voyager-ticket',
            'Módulos por servicio' => 'voyager-tools',
            'Configuración' => 'voyager-settings',
            'Años' => 'voyager-calendar',
            'Acerca de Centinela' => 'voyager-info-circled',
            'Cortes' => 'voyager-scissors',
            'Ámbitos de indicadores' => 'voyager-tag',
            'Instrumentos locales' => 'voyager-lab',
            'Pestaña instrumento' => 'voyager-tag',
            'Unidades (metas)' => 'voyager-list',
            'Paneles públicos' => 'voyager-news',
        ];
    }

    private function ensureMenu(string $name, Carbon $now): int
    {
        $id = DB::table('menus')->where('name', $name)->value('id');

        if ($id !== null) {
            return (int) $id;
        }

        return (int) DB::table('menus')->insertGetId([
            'name' => $name,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param  array{title: string, icon_class: string, order: int, route: ?string}  $attrs
     */
    private function upsertMenuItem(
        int $menuId,
        ?int $parentId,
        array $attrs,
        Carbon $now,
        bool $hasRoute,
        bool $hasParameters,
    ): int {
        $query = DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->where('title', $attrs['title']);

        if ($parentId === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $parentId);
        }

        if ($hasRoute) {
            if ($attrs['route'] === null) {
                $query->whereNull('route');
            } else {
                $query->where('route', $attrs['route']);
            }
        }

        $existingId = $query->value('id');

        $row = [
            'menu_id' => $menuId,
            'title' => $attrs['title'],
            'url' => '',
            'target' => '_self',
            'icon_class' => $attrs['icon_class'],
            'color' => null,
            'parent_id' => $parentId,
            'order' => $attrs['order'],
            'updated_at' => $now,
        ];

        if ($hasRoute) {
            $row['route'] = $attrs['route'];
        }

        if ($hasParameters) {
            $row['parameters'] = null;
        }

        if ($existingId !== null) {
            DB::table('menu_items')->where('id', $existingId)->update($row);

            return (int) $existingId;
        }

        $row['created_at'] = $now;

        return (int) DB::table('menu_items')->insertGetId($row);
    }

    private function seedMinimalSettings(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $settings = [
            [
                'key' => 'site.title',
                'display_name' => 'Título del sitio',
                'value' => 'Centinela',
                'details' => '',
                'type' => 'text',
                'order' => 1,
                'group' => 'Site',
            ],
            [
                'key' => 'site.description',
                'display_name' => 'Descripción del sitio',
                'value' => 'Plataforma de indicadores para Servicios de Salud',
                'details' => '',
                'type' => 'text',
                'order' => 2,
                'group' => 'Site',
            ],
            [
                'key' => 'admin.title',
                'display_name' => 'Título del administrador',
                'value' => 'Centinela',
                'details' => '',
                'type' => 'text',
                'order' => 1,
                'group' => 'Admin',
            ],
            [
                'key' => 'admin.description',
                'display_name' => 'Descripción del administrador',
                'value' => 'La plataforma de visualización de indicadores para los Servicios de Salud.',
                'details' => '',
                'type' => 'text',
                'order' => 2,
                'group' => 'Admin',
            ],
            [
                'key' => 'admin.modo_mantenimiento',
                'display_name' => 'Modo mantenimiento',
                'value' => 'Off',
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"}}',
                'type' => 'radio_btn',
                'order' => 7,
                'group' => 'Admin',
            ],
            [
                'key' => 'admin.voyager_compass_enabled',
                'display_name' => 'Habilitar Compass de Voyager',
                'value' => 'Off',
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"},"description":"Permite acceder a /admin/compass fuera del entorno local (herramientas Artisan, logs y caché de Voyager)."}',
                'type' => 'radio_btn',
                'order' => 9,
                'group' => 'Admin',
            ],
            [
                'key' => 'services.github_releases_repo',
                'display_name' => 'Repositorio GitHub (owner/repo)',
                'value' => 'jcalistop/centinela-public',
                'details' => '{"description":"Repositorio en formato owner/repo (ej: jcalistop/centinela-public) usado para consultar releases desde el widget del dashboard."}',
                'type' => 'text',
                'order' => 5,
                'group' => 'Servicios externos',
            ],
            [
                'key' => 'services.git_binary_path',
                'display_name' => 'Ruta al binario de Git',
                'value' => '',
                'details' => '{"description":"Ruta absoluta o comando del binario de Git en el servidor. Por defecto usa git (debe estar en PATH)."}',
                'type' => 'text',
                'order' => 4,
                'group' => 'Deploy',
            ],
            [
                'key' => 'services.db_dump_path',
                'display_name' => 'Ruta al binario mysqldump',
                'value' => '',
                'details' => '{"description":"Ruta absoluta al binario mysqldump para generar respaldos de base de datos. Vacío si está en PATH."}',
                'type' => 'text',
                'order' => 5,
                'group' => 'Sistema',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
