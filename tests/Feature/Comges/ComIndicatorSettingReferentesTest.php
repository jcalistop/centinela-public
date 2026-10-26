<?php

namespace Tests\Feature\Comges;

use App\Models\ComIndicatorSetting;
use App\Models\IndicatorType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ComIndicatorSettingReferentesTest extends TestCase
{
    use RefreshDatabase;

    private int $dependencyId;

    private User $userA;

    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencyId = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-REF',
            'nombre' => 'Servicio referentes',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $role = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $this->userA = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => $this->dependencyId,
        ]);
        $this->userB = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => $this->dependencyId,
        ]);
    }

    public function test_unique_index_exists_on_pivot(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = collect(DB::select("PRAGMA index_list('com_indicator_setting_user')"))
                ->pluck('name')
                ->all();
        } else {
            $indexes = collect(DB::select('SHOW INDEX FROM `com_indicator_setting_user`'))
                ->pluck('Key_name')
                ->unique()
                ->all();
        }

        $this->assertContains('uq_com_ind_set_user', $indexes);
    }

    public function test_unique_constraint_rejects_duplicate_pivot_row(): void
    {
        $settingId = $this->seedSetting();

        DB::table('com_indicator_setting_user')->insert([
            'com_indicator_setting_id' => $settingId,
            'user_id' => $this->userA->id,
        ]);

        $this->expectException(QueryException::class);

        DB::table('com_indicator_setting_user')->insert([
            'com_indicator_setting_id' => $settingId,
            'user_id' => $this->userA->id,
        ]);
    }

    public function test_sync_with_duplicate_ids_leaves_one_row_per_user(): void
    {
        $setting = ComIndicatorSetting::query()->findOrFail($this->seedSetting());

        $setting->users()->sync([
            $this->userA->id,
            $this->userA->id,
            $this->userB->id,
            $this->userB->id,
        ]);

        $this->assertSame(
            2,
            (int) DB::table('com_indicator_setting_user')
                ->where('com_indicator_setting_id', $setting->id)
                ->count()
        );
        $this->assertEqualsCanonicalizing(
            [$this->userA->id, $this->userB->id],
            DB::table('com_indicator_setting_user')
                ->where('com_indicator_setting_id', $setting->id)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all()
        );
    }

    public function test_collapse_duplicate_pivot_rows_via_detach_reattach(): void
    {
        $setting = ComIndicatorSetting::query()->findOrFail($this->seedSetting());

        Schema::table('com_indicator_setting_user', function ($table): void {
            $table->dropUnique('uq_com_ind_set_user');
        });

        DB::table('com_indicator_setting_user')->insert([
            ['com_indicator_setting_id' => $setting->id, 'user_id' => $this->userA->id],
            ['com_indicator_setting_id' => $setting->id, 'user_id' => $this->userA->id],
            ['com_indicator_setting_id' => $setting->id, 'user_id' => $this->userA->id],
            ['com_indicator_setting_id' => $setting->id, 'user_id' => $this->userB->id],
        ]);

        $this->assertSame(4, $setting->users()->count());

        $uniqueIds = $setting->users()->pluck('users.id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $setting->users()->detach();
        $setting->users()->attach($uniqueIds);

        Schema::table('com_indicator_setting_user', function ($table): void {
            $table->unique(['com_indicator_setting_id', 'user_id'], 'uq_com_ind_set_user');
        });

        $this->assertSame(2, $setting->fresh()->users()->count());
    }

    public function test_dedupe_migration_is_idempotent_on_clean_table(): void
    {
        $settingId = $this->seedSetting();
        DB::table('com_indicator_setting_user')->insert([
            'com_indicator_setting_id' => $settingId,
            'user_id' => $this->userA->id,
        ]);

        $before = (int) DB::table('com_indicator_setting_user')->count();

        $migration = require database_path('migrations/2026_08_07_140409_dedupe_and_unique_com_indicator_setting_user.php');
        $migration->up();

        $this->assertSame($before, (int) DB::table('com_indicator_setting_user')->count());
        $this->assertDatabaseHas('com_indicator_setting_user', [
            'com_indicator_setting_id' => $settingId,
            'user_id' => $this->userA->id,
        ]);
    }

    private function seedSetting(): int
    {
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

        $yearId = (int) (DB::table('years')->where('ano', 2026)->value('id')
            ?? DB::table('years')->insertGetId([
                'ano' => 2026,
                'default' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

        $commitmentId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => 1,
            'clave' => 'C-REF-'.bin2hex(random_bytes(3)),
            'nombre' => 'Compromiso ref',
            'descripcion' => null,
            'orden' => 1,
            'year_id' => $yearId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentId,
            'clave' => 'IND-REF-'.bin2hex(random_bytes(3)),
            'nombre' => 'Indicador ref',
            'ponderacion_indicador' => 100,
            'descripcion' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('com_indicator_settings')->insertGetId([
            'cen_hie_dependency_id' => $this->dependencyId,
            'com_indicator_id' => $indicatorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
