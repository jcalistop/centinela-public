<?php

namespace Tests\Feature\Seeders;

use App\Models\User;
use Database\Seeders\CentinelaSqlTableSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UsableCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class UsableCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_loads_allow_list_and_keeps_deny_list_empty(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, DB::table('cen_centers')->count());
        $this->assertGreaterThan(0, DB::table('cen_addresses')->count());
        $this->assertGreaterThan(0, DB::table('cen_hie_dependencies')->where('tipo_dependencia', 'SERVICIO')->count());
        $this->assertGreaterThan(0, DB::table('com_commitments')->count());
        $this->assertGreaterThan(0, DB::table('com_indicators')->count());
        $this->assertGreaterThan(0, DB::table('com_actions')->count());
        $this->assertGreaterThan(0, DB::table('cutoffs')->count());
        $this->assertGreaterThan(0, DB::table('indicator_types')->count());
        if (Schema::hasTable('github_release_snapshots')) {
            $this->assertGreaterThan(0, DB::table('github_release_snapshots')->count());
        }

        // SDD-241 Metas maestra
        if (Schema::hasTable('met_indicators')) {
            $this->assertGreaterThan(0, DB::table('met_unit_types')->count());
            $this->assertGreaterThan(0, DB::table('met_indicators')->count());
            $this->assertGreaterThan(0, DB::table('met_data_fields')->count());
            $this->assertGreaterThan(0, DB::table('met_formulas')->count());
        }
        if (Schema::hasTable('indicator_type_global_settings')) {
            $this->assertGreaterThan(0, DB::table('indicator_type_global_settings')->count());
        }

        if (Schema::hasTable('com_evaluations')) {
            $this->assertSame(0, DB::table('com_evaluations')->count());
        }
        if (Schema::hasTable('com_verifiable_documents')) {
            $this->assertSame(0, DB::table('com_verifiable_documents')->count());
        }
        if (Schema::hasTable('com_indicator_settings')) {
            $this->assertSame(0, DB::table('com_indicator_settings')->count());
        }
        if (Schema::hasTable('met_monthly_data')) {
            $this->assertSame(0, DB::table('met_monthly_data')->count());
        }
        if (Schema::hasTable('met_results')) {
            $this->assertSame(0, DB::table('met_results')->count());
        }
        if (Schema::hasTable('met_establishment_agreements')) {
            $this->assertSame(0, DB::table('met_establishment_agreements')->count());
        }
        if (Schema::hasTable('met_indicator_configurations')) {
            $this->assertSame(0, DB::table('met_indicator_configurations')->count());
        }
        if (Schema::hasTable('met_measurement_units')) {
            $this->assertSame(0, DB::table('met_measurement_units')->count());
        }
        if (Schema::hasTable('met_field_ss_configs')) {
            $this->assertSame(0, DB::table('met_field_ss_configs')->count());
        }

        $admin = User::query()->where('email', 'admin@centinela.cl')->first();
        $this->assertNotNull($admin);
    }

    public function test_metas_prerequisites_fail_fast_when_years_empty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Prerequisitos SDD-240');

        $seeder = new UsableCatalogSeeder;
        $method = new ReflectionMethod(UsableCatalogSeeder::class, 'assertMetasPrerequisites');
        $method->setAccessible(true);
        $method->invoke($seeder);
    }

    public function test_minimal_platform_does_not_pollute_catalog_admin_menu(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Fixtures testing no traen Parametrización → MinimalPlatform sí inyecta menú Voyager.
        // Con menú de catálogo (título Parametrización), no debe quedar Media ni grupo Configuración.
        if (! DB::table('menu_items')->whereNull('parent_id')->where('title', 'Parametrización')->exists()) {
            $this->markTestSkipped('Fixture testing sin menú Parametrización (camino MinimalPlatform completo).');
        }

        $this->assertSame(
            0,
            DB::table('menu_items')->where('route', 'voyager.media.index')->count()
        );
        $this->assertSame(
            0,
            DB::table('menu_items')
                ->whereNull('parent_id')
                ->where('title', 'Configuración')
                ->where(function ($q): void {
                    $q->whereNull('route')->orWhere('route', '');
                })
                ->count()
        );
        $this->assertLessThanOrEqual(
            1,
            DB::table('menu_items')->where('route', 'voyager.settings.index')->count()
        );

        $paramOrder = (int) DB::table('menu_items')
            ->whereNull('parent_id')
            ->where('title', 'Parametrización')
            ->value('order');
        $toolsOrder = (int) DB::table('menu_items')
            ->whereNull('parent_id')
            ->where('title', 'Herramientas')
            ->value('order');
        $this->assertLessThan($toolsOrder, $paramOrder);
    }

    public function test_centinela_sql_table_seeder_is_retired_stub(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('retirado');

        $this->seed(CentinelaSqlTableSeeder::class);
    }
}
