<?php

declare(strict_types=1);

namespace Tests\Feature\Voyager;

use App\Models\CenHieDependency;
use App\Models\User;
use App\Models\Year;
use App\Support\Voyager\VoyagerSchemaDescriber;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use TCG\Voyager\Models\DataType;
use TCG\Voyager\Models\Permission;
use TCG\Voyager\Models\Role;
use Tests\TestCase;

class VoyagerSchemaToolsTest extends TestCase
{
    use RefreshDatabase;

    private const PROBE_TABLE = 'voyager_schema_probe';

    private Year $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = Year::unguarded(fn () => Year::create([
            'ano' => 2026,
            'default' => true,
        ]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        Schema::create(self::PROBE_TABLE, function (Blueprint $table): void {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre')->index();
            $table->string('glosa')->nullable();
            $table->unsignedBigInteger('orden')->index();
            $table->index(['nombre', 'glosa']);
        });
    }

    public function test_describe_table_handles_indexes_beyond_the_second_position(): void
    {
        $ordenIndexPosition = collect(Schema::getIndexes(self::PROBE_TABLE))
            ->filter(fn (array $index): bool => $index['columns'] === ['orden'])
            ->keys()
            ->first();
        $this->assertGreaterThanOrEqual(2, $ordenIndexPosition, 'Premisa: el índice de `orden` debe quedar en posición ≥ 2.');

        $columns = VoyagerSchemaDescriber::describeTable(self::PROBE_TABLE)->keyBy('field');

        $this->assertSame(['id', 'codigo', 'nombre', 'glosa', 'orden'], $columns->keys()->all());

        $this->assertSame('PRI', $columns['id']['key']);
        $this->assertSame('NO', $columns['id']['null']);
        $this->assertSame('auto_increment', $columns['id']['extra']);

        $this->assertSame('UNI', $columns['codigo']['key']);
        $this->assertSame('IND', $columns['nombre']['key']);
        $this->assertSame('IND', $columns['orden']['key']);
        $this->assertSame('', $columns['orden']['extra']);

        $this->assertNull($columns['glosa']['key'], 'Un índice compuesto no define `key` (semántica de Voyager 1.7).');
        $this->assertSame('YES', $columns['glosa']['null']);
    }

    public function test_bread_create_renders_and_leaves_primary_key_unchecked_by_default(): void
    {
        $response = $this->actingAsSchemaAdmin()
            ->get('/admin/bread/'.self::PROBE_TABLE.'/create')
            ->assertOk()
            ->assertSee('<span>PRI</span>', false);

        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/name="field_browse_id"\s*>/', $html);
        $this->assertMatchesRegularExpression('/name="field_browse_codigo"\s*checked="checked"/', $html);
    }

    public function test_bread_edit_renders_for_an_existing_data_type(): void
    {
        DataType::unguarded(fn () => DataType::create([
            'name' => self::PROBE_TABLE,
            'slug' => 'voyager-schema-probe',
            'display_name_singular' => 'Sonda',
            'display_name_plural' => 'Sondas',
            'model_name' => '',
            'generate_permissions' => 0,
            'server_side' => 0,
            'details' => [
                'order_column' => null,
                'order_display_column' => null,
                'order_direction' => 'asc',
                'default_search_key' => null,
            ],
        ]));

        $response = $this->actingAsSchemaAdmin()
            ->get('/admin/bread/'.self::PROBE_TABLE.'/edit')
            ->assertOk()
            ->assertSee('<span>PRI</span>', false);

        $this->assertMatchesRegularExpression('/name="field_browse_id"\s*>/', $response->getContent());
    }

    public function test_database_index_and_show_are_available_read_only(): void
    {
        // El listado enlaza al browse de cada BREAD, cuyas rutas Voyager registra al boot desde
        // `data_types`; en PHPUnit esa tabla aún está vacía al boot y las rutas no existen.
        DataType::query()->delete();

        $this->actingAsSchemaAdmin()
            ->get('/admin/database')
            ->assertOk()
            ->assertSee(self::PROBE_TABLE);

        $columns = collect(
            $this->actingAsSchemaAdmin()
                ->get('/admin/database/'.self::PROBE_TABLE)
                ->assertOk()
                ->json()
        )->keyBy('field');

        $this->assertSame('PRI', $columns['id']['key']);
        $this->assertSame('NO', $columns['id']['null']);
        $this->assertSame('YES', $columns['glosa']['null']);
    }

    /**
     * @param  array<string, string>  $payload
     */
    #[DataProvider('schemaChangeRequests')]
    public function test_schema_changes_are_rejected_without_touching_the_schema(string $method, string $uri, array $payload): void
    {
        $columnsBefore = Schema::getColumnListing(self::PROBE_TABLE);

        $response = $this->actingAsSchemaAdmin()->call($method, $uri, $payload);

        $response->assertRedirect(route('voyager.database.index'));
        $alerts = $response->getSession()->get('alerts');
        $this->assertSame('error', $alerts[0]['type'] ?? null);
        $this->assertStringContainsString('migraciones', (string) ($alerts[0]['message'] ?? ''));

        $this->assertTrue(Schema::hasTable(self::PROBE_TABLE));
        $this->assertSame($columnsBefore, Schema::getColumnListing(self::PROBE_TABLE));
        $this->assertFalse(Schema::hasTable('voyager_schema_new'));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<string, string>}>
     */
    public static function schemaChangeRequests(): array
    {
        $newTable = json_encode(['name' => 'voyager_schema_new', 'columns' => [], 'indexes' => [], 'foreignKeys' => [], 'options' => []]);
        $alteredProbe = json_encode(['name' => self::PROBE_TABLE, 'oldName' => self::PROBE_TABLE, 'columns' => [], 'indexes' => [], 'foreignKeys' => [], 'options' => []]);

        return [
            'create' => ['GET', '/admin/database/create', []],
            'store' => ['POST', '/admin/database', ['table' => $newTable]],
            'edit' => ['GET', '/admin/database/'.self::PROBE_TABLE.'/edit', []],
            'update' => ['PUT', '/admin/database/'.self::PROBE_TABLE, ['table' => $alteredProbe]],
            'destroy' => ['DELETE', '/admin/database/'.self::PROBE_TABLE, []],
        ];
    }

    public function test_schema_change_without_database_permission_is_forbidden(): void
    {
        $this->actingAsSchemaAdmin(grantAllAbilities: false)
            ->delete('/admin/database/'.self::PROBE_TABLE)
            ->assertForbidden();

        $this->assertTrue(Schema::hasTable(self::PROBE_TABLE));
    }

    private function actingAsSchemaAdmin(bool $grantAllAbilities = true): static
    {
        if ($grantAllAbilities) {
            Gate::before(fn (): ?bool => true);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $browseAdmin = Permission::firstOrCreate(['key' => 'browse_admin'], ['table_name' => null]);
        $adminRole->permissions()->syncWithoutDetaching([$browseAdmin->id]);

        $user = User::query()->firstWhere('email', 'schema-admin@test.local')
            ?? User::unguarded(fn () => User::create([
                'name' => 'Admin Esquema',
                'email' => 'schema-admin@test.local',
                'password' => Hash::make('password'),
                'role_id' => $adminRole->id,
                'cen_hie_dependency_id' => 1,
            ]));
        $user->role()->associate($adminRole)->save();

        return $this->actingAs($user)->withSession(['indicator_year' => $this->year]);
    }
}
