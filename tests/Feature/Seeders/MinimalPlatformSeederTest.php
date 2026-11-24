<?php

namespace Tests\Feature\Seeders;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MinimalPlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MinimalPlatformSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_bootstraps_roles_dependency_and_admin_without_sql_dump(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('roles', ['id' => 1, 'name' => 'admin']);
        $this->assertDatabaseHas('roles', ['id' => 5, 'name' => 'parametrizador']);
        // Con UsableCatalog CEN, id 1 suele ser «No Aplica»; no se fuerza «SS de prueba».
        $this->assertDatabaseHas('cen_hie_dependencies', [
            'id' => 1,
        ]);
        $this->assertGreaterThan(0, DB::table('cen_hie_dependencies')->count());
        $this->assertDatabaseHas('permissions', ['key' => 'browse_admin']);
        $this->assertTrue(
            DB::table('permission_role')
                ->where('role_id', 1)
                ->whereIn('permission_id', DB::table('permissions')->where('key', 'browse_admin')->pluck('id'))
                ->exists()
        );
        $this->assertDatabaseHas('menus', ['name' => 'admin']);
        $this->assertDatabaseHas('menus', ['name' => 'inicio']);
        $this->assertDatabaseHas('data_types', ['slug' => 'users', 'name' => 'users']);
        $this->assertDatabaseHas('data_types', ['slug' => 'roles', 'name' => 'roles']);
        $this->assertDatabaseHas('permissions', ['key' => 'browse_users']);
        $this->assertDatabaseHas('permissions', ['key' => 'browse_roles']);
        $this->assertDatabaseHas('menu_items', ['title' => 'Inicio', 'route' => 'voyager.dashboard']);
        $this->assertDatabaseHas('menu_items', ['title' => 'Usuarios', 'route' => 'voyager.users.index']);
        // Con menú de catálogo (Parametrización), MinimalPlatform no inyecta Media/Config Voyager.
        $this->assertDatabaseHas('menu_items', ['title' => 'Parametrización']);
        $this->assertDatabaseHas('menu_items', [
            'title' => 'Paneles públicos',
            'route' => 'centinela.admin.paneles-publicos',
        ]);
        $this->assertDatabaseHas('permissions', ['key' => 'browse_paneles-publicos']);
        $this->assertTrue(
            DB::table('permission_role')
                ->where('role_id', 1)
                ->whereIn('permission_id', DB::table('permissions')->where('key', 'browse_paneles-publicos')->pluck('id'))
                ->exists()
        );
        $this->assertDatabaseHas('menu_items', [
            'title' => 'Configuración',
            'route' => 'voyager.settings.index',
        ]);
        $this->assertDatabaseMissing('menu_items', ['route' => 'voyager.media.index']);
        $this->assertDatabaseMissing('menu_items', ['title' => 'Ajustes del sitio']);
        $this->assertGreaterThanOrEqual(5, DB::table('menu_items')->count());
        $this->assertDatabaseHas('settings', ['key' => 'admin.title', 'value' => 'Centinela']);
        $this->assertDatabaseHas('settings', ['key' => 'admin.voyager_compass_enabled', 'value' => 'Off']);
        $this->assertDatabaseHas('settings', [
            'key' => 'services.github_releases_repo',
            'value' => 'jcalistop/centinela-public',
        ]);
        $this->assertDatabaseHas('settings', ['key' => 'services.git_binary_path', 'value' => '']);
        $this->assertDatabaseHas('settings', ['key' => 'services.db_dump_path', 'value' => '']);
        $this->assertDatabaseHas('settings', [
            'key' => 'instance.ss_display_name',
            'value' => 'SS de prueba',
            'group' => 'Servicio de Salud',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'instance.ss_url',
            'value' => '',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'instance.unit_name',
            'value' => 'Departamento de Control de Gestión',
        ]);
        $this->assertDatabaseHas('years', [
            'ano' => (int) now()->year,
            'default' => 1,
        ]);

        $admin = User::query()->where('email', 'admin@centinela.cl')->first();
        $this->assertNotNull($admin);
        $this->assertSame(1, (int) $admin->role_id);
        $this->assertSame(1, (int) $admin->cen_hie_dependency_id);
    }

    public function test_minimal_platform_seeds_ss_de_prueba_when_dependencies_empty(): void
    {
        DB::table('cen_hie_dependencies')->delete();

        $this->seed(MinimalPlatformSeeder::class);

        $this->assertDatabaseHas('cen_hie_dependencies', [
            'codigo' => 'SS-PRUEBA',
            'nombre' => 'SS de prueba',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => 1,
        ]);
    }

    public function test_database_seeder_loads_catalog_addresses_without_evaluations(): void
    {
        $this->seed(DatabaseSeeder::class);

        // SDD-240: allow-list CEN incluye addresses; deny-list COMGES evaluaciones queda vacío.
        $this->assertGreaterThan(0, DB::table('cen_addresses')->count());
        if (DB::getSchemaBuilder()->hasTable('com_evaluations')) {
            $this->assertSame(0, DB::table('com_evaluations')->count());
        }
    }
}
