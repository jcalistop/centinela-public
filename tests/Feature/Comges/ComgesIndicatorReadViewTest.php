<?php

namespace Tests\Feature\Comges;

use App\Http\Controllers\Voyager\ComIndicatorController;
use App\Models\ComIndicator;
use App\Models\ComIndicatorSetting;
use App\Models\IndicatorAmbito;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Indicators\IndicatorAmbitoAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use TCG\Voyager\Models\DataType;
use TCG\Voyager\Models\Permission;
use Tests\Support\CreatesMinimalDocumentsBread;
use Tests\TestCase;

class ComgesIndicatorReadViewTest extends TestCase
{
    use CreatesMinimalDocumentsBread;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => $this->seedDependency(),
        ]);
        $this->admin->role()->associate($adminRole)->save();
        $this->grantBrowseAdminPermissionToRole($adminRole);
        $this->seedIndicadoresBread($adminRole);
        $this->registerVoyagerBreadPolicyForModel(ComIndicator::class);

        IndicatorType::unguarded(fn () => IndicatorType::updateOrCreate(
            ['id' => 1],
            [
                'clave_indicador' => 'comges',
                'nombre_indicador' => 'COMGES',
                'route' => 'voyager.compromisos.index',
                'estado' => true,
                'orden' => 1,
            ]
        ));
    }

    public function test_read_uses_centinela_comges_view_for_year_2026(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $this->seedInstrumentSetting((int) $year->id);
        $indicatorId = $this->seedIndicator((int) $year->id);

        $response = $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $year])
            ->get('/admin/indicadores-comges/'.$indicatorId);

        $response->assertOk();
        $response->assertSee('indicador-ficha-nombre', false);
        $response->assertDontSee('indicator-container', false);
    }

    public function test_read_shows_referente_profile_link_for_year_2026(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $this->seedInstrumentSetting((int) $year->id);
        $indicatorId = $this->seedIndicator((int) $year->id);

        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => $this->admin->cen_hie_dependency_id,
            'name' => 'Referente COMGES Enlace',
        ]);
        $referente->role()->associate($referenteRole)->save();

        $setting = ComIndicatorSetting::unguarded(fn () => ComIndicatorSetting::create([
            'cen_hie_dependency_id' => $this->admin->cen_hie_dependency_id,
            'com_indicator_id' => $indicatorId,
        ]));
        $setting->users()->attach($referente->id);

        $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $year])
            ->get('/admin/indicadores-comges/'.$indicatorId)
            ->assertOk()
            ->assertSee('Referente COMGES Enlace', false)
            ->assertSee(route('voyager.users.show', $referente->id), false);
    }

    public function test_read_shows_linked_ambito_badge_when_dashboard_enabled(): void
    {
        config()->set('centinela.dashboard_non_admin.enabled', true);

        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $this->seedInstrumentSetting((int) $year->id);
        $indicatorId = $this->seedIndicator((int) $year->id);

        $ambito = IndicatorAmbito::query()->where('slug', 'ges')->firstOrFail();
        $indicator = ComIndicator::query()->findOrFail($indicatorId);
        app(IndicatorAmbitoAssignmentService::class)->sync($indicator, [$ambito->id]);

        $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $year])
            ->get('/admin/indicadores-comges/'.$indicatorId)
            ->assertOk()
            ->assertSee($ambito->nombre, false)
            ->assertSee('/admin/dashboard/ambitos/'.$ambito->slug, false);
    }

    public function test_read_shows_plain_ambito_badge_when_dashboard_disabled(): void
    {
        config()->set('centinela.dashboard_non_admin.enabled', false);

        $year = Year::unguarded(fn () => Year::create(['ano' => 2024, 'default' => 1]));
        $this->seedInstrumentSetting((int) $year->id);
        $indicatorId = $this->seedIndicator((int) $year->id);

        $ambito = IndicatorAmbito::query()->where('slug', 'ges')->firstOrFail();
        $indicator = ComIndicator::query()->findOrFail($indicatorId);
        app(IndicatorAmbitoAssignmentService::class)->sync($indicator, [$ambito->id]);

        $response = $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $year])
            ->get('/admin/indicadores-comges/'.$indicatorId);

        $response->assertOk()->assertSee($ambito->nombre, false);
        $this->assertStringNotContainsString('/admin/dashboard/ambitos/', $response->getContent());
    }

    public function test_read_uses_voyager_legacy_view_for_year_2024(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2024, 'default' => 1]));
        $this->seedInstrumentSetting((int) $year->id);
        $indicatorId = $this->seedIndicator((int) $year->id);

        $response = $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $year])
            ->get('/admin/indicadores-comges/'.$indicatorId);

        $response->assertOk();
        $response->assertSee('indicator-container', false);
        $response->assertDontSee('indicador-ficha-nombre', false);
    }

    public function test_read_shows_referente_profile_link_for_year_2024_legacy_view(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2024, 'default' => 1]));
        $this->seedInstrumentSetting((int) $year->id);
        $indicatorId = $this->seedIndicator((int) $year->id);

        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => $this->admin->cen_hie_dependency_id,
            'name' => 'Referente Legacy Enlace',
        ]);
        $referente->role()->associate($referenteRole)->save();

        $setting = ComIndicatorSetting::unguarded(fn () => ComIndicatorSetting::create([
            'cen_hie_dependency_id' => $this->admin->cen_hie_dependency_id,
            'com_indicator_id' => $indicatorId,
        ]));
        $setting->users()->attach($referente->id);

        $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $year])
            ->get('/admin/indicadores-comges/'.$indicatorId)
            ->assertOk()
            ->assertSee('Referente Legacy Enlace', false)
            ->assertSee(route('voyager.users.show', $referente->id), false);
    }

    private function seedInstrumentSetting(int $yearId): void
    {
        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::updateOrCreate(
            [
                'indicator_type_id' => 1,
                'year_id' => $yearId,
                'cen_hie_dependency_id' => $this->admin->cen_hie_dependency_id,
            ],
            ['habilitado' => true]
        ));
    }

    private function seedIndicator(int $yearId): int
    {
        $suffix = bin2hex(random_bytes(4));

        $commitmentId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => 1,
            'clave' => 'C-'.$suffix,
            'nombre' => 'Compromiso',
            'descripcion' => null,
            'orden' => 1,
            'year_id' => $yearId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentId,
            'clave' => 'IND-'.$suffix,
            'nombre' => 'Indicador',
            'ponderacion_indicador' => 100,
            'descripcion' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedIndicadoresBread(Role $role): void
    {
        if (! Schema::hasTable('data_types')) {
            return;
        }

        $dataType = DataType::unguarded(function (): DataType {
            return DataType::query()->updateOrCreate(
                ['slug' => 'indicadores-comges'],
                [
                    'name' => 'com_indicators',
                    'display_name_singular' => 'Indicador COMGES',
                    'display_name_plural' => 'Indicadores COMGES',
                    'model_name' => ComIndicator::class,
                    'controller' => ComIndicatorController::class,
                    'generate_permissions' => 0,
                    'server_side' => 0,
                    'details' => json_encode([]),
                ]
            );
        });

        $permissionId = Permission::firstOrCreate(
            ['key' => 'read_'.$dataType->name],
            ['table_name' => $dataType->name]
        )->id;
        $role->permissions()->syncWithoutDetaching([$permissionId]);
    }

    private function seedDependency(): int
    {
        return (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-READ-VIEW',
            'nombre' => 'Servicio read view',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
