<?php

namespace Tests\Feature\Comges;

use App\Http\Controllers\Voyager\ComIndicatorController;
use App\Livewire\Comges\StructureOrderComponent;
use App\Models\ComCommitment;
use App\Models\ComIndicator;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Comges\ComIndicatorPlacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use TCG\Voyager\Models\DataRow;
use TCG\Voyager\Models\DataType;
use TCG\Voyager\Models\Permission;
use Tests\Support\CreatesMinimalDocumentsBread;
use Tests\TestCase;

class ComgesStructureOrderTest extends TestCase
{
    use CreatesMinimalDocumentsBread;
    use RefreshDatabase;

    private Year $year;

    private User $admin;

    private User $referente;

    protected function setUp(): void
    {
        parent::setUp();

        $dependencyId = $this->seedDependency();

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => $dependencyId,
        ]);
        $this->admin->role()->associate($adminRole)->save();

        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $this->referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => $dependencyId,
        ]);
        $this->referente->role()->associate($referenteRole)->save();

        $this->grantBrowseAdminPermissionToRole($adminRole);
        $this->grantIndicadorBreadPermissions($adminRole, edit: true);

        $this->grantBrowseAdminPermissionToRole($referenteRole);
        $this->grantIndicadorBreadPermissions($referenteRole, edit: false);

        $this->seedIndicadoresBread();
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

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::updateOrCreate(
            [
                'indicator_type_id' => 1,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => $dependencyId,
            ],
            ['habilitado' => true]
        ));
    }

    public function test_commitment_relationship_orders_indicators_by_orden_then_clave(): void
    {
        [$commitmentAId] = $this->seedCommitments();
        $suffix = uniqid();

        DB::table('com_indicators')->insert([
            [
                'com_commitment_id' => $commitmentAId,
                'clave' => 'Z-'.$suffix,
                'nombre' => 'Zeta',
                'ponderacion_indicador' => 50,
                'descripcion' => null,
                'orden' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'com_commitment_id' => $commitmentAId,
                'clave' => 'A-'.$suffix,
                'nombre' => 'Alfa',
                'ponderacion_indicador' => 50,
                'descripcion' => null,
                'orden' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $ordered = ComCommitment::query()
            ->with('comIndicators')
            ->findOrFail($commitmentAId)
            ->comIndicators
            ->pluck('clave')
            ->all();

        $this->assertSame(['A-'.$suffix, 'Z-'.$suffix], $ordered);
    }

    public function test_apply_structure_reorders_indicators_within_commitment(): void
    {
        [$commitmentAId, $indicatorIds] = $this->seedIndicatorsStructure();

        app(ComIndicatorPlacementService::class)->applyStructure([
            ['id' => $commitmentAId, 'indicator_ids' => array_reverse($indicatorIds)],
        ], $this->year);

        $orden = ComIndicator::query()
            ->whereIn('id', $indicatorIds)
            ->orderBy('orden')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertSame(array_reverse($indicatorIds), $orden);
    }

    public function test_apply_structure_moves_indicator_between_commitments(): void
    {
        [$commitmentAId, $commitmentBId, $indicatorA1, $indicatorA2, $indicatorB1] = $this->seedTwoCommitmentsWithIndicators();

        app(ComIndicatorPlacementService::class)->applyStructure([
            ['id' => $commitmentAId, 'indicator_ids' => [$indicatorA2]],
            ['id' => $commitmentBId, 'indicator_ids' => [$indicatorB1, $indicatorA1]],
        ], $this->year);

        $this->assertSame($commitmentAId, (int) ComIndicator::query()->findOrFail($indicatorA2)->com_commitment_id);
        $this->assertSame($commitmentBId, (int) ComIndicator::query()->findOrFail($indicatorA1)->com_commitment_id);
        $this->assertSame(2, (int) ComIndicator::query()->findOrFail($indicatorA1)->orden);
        $this->assertSame(1, (int) ComIndicator::query()->findOrFail($indicatorB1)->orden);
    }

    public function test_apply_structure_rejects_missing_indicator_ids(): void
    {
        [$commitmentAId, $indicatorIds] = $this->seedIndicatorsStructure();

        $this->expectException(ValidationException::class);

        app(ComIndicatorPlacementService::class)->applyStructure([
            ['id' => $commitmentAId, 'indicator_ids' => [$indicatorIds[0]]],
        ], $this->year);
    }

    public function test_apply_structure_rejects_commitment_from_other_year(): void
    {
        [$commitmentAId, $indicatorIds] = $this->seedIndicatorsStructure();
        $otherYear = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 0]));
        $otherCommitmentId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => 1,
            'clave' => 'CO-'.uniqid(),
            'nombre' => 'Otro año',
            'descripcion' => null,
            'orden' => 1,
            'year_id' => $otherYear->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(ValidationException::class);

        app(ComIndicatorPlacementService::class)->applyStructure([
            ['id' => $otherCommitmentId, 'indicator_ids' => $indicatorIds],
        ], $this->year);
    }

    public function test_user_without_edit_cannot_access_structure_order_page(): void
    {
        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin/compromisos/estructura')
            ->assertForbidden();
    }

    public function test_admin_can_access_structure_order_page(): void
    {
        $this->seedIndicatorsStructure();

        $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin/compromisos/estructura')
            ->assertOk()
            ->assertSee('Ordenar estructura COMGES', false)
            ->assertSeeLivewire(StructureOrderComponent::class);
    }

    public function test_admin_can_apply_structure_via_livewire(): void
    {
        [$commitmentAId, $indicatorIds] = $this->seedIndicatorsStructure();

        $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $this->year]);

        Livewire::test(StructureOrderComponent::class)
            ->call('applyStructure', [
                'commitments' => [
                    ['id' => $commitmentAId, 'indicator_ids' => array_reverse($indicatorIds)],
                ],
            ])
            ->assertHasNoErrors();

        $firstOrdenId = (int) ComIndicator::query()
            ->where('com_commitment_id', $commitmentAId)
            ->where('orden', 1)
            ->value('id');

        $this->assertSame($indicatorIds[1], $firstOrdenId);
    }

    public function test_relocate_via_update_places_indicator_at_end_of_destination_commitment(): void
    {
        [$commitmentAId, $commitmentBId, $indicatorA1, $indicatorA2, $indicatorB1] = $this->seedTwoCommitmentsWithIndicators();

        $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $this->year])
            ->put('/admin/indicadores-comges/'.$indicatorA1, [
                'com_commitment_id' => $commitmentBId,
                'clave' => ComIndicator::query()->findOrFail($indicatorA1)->clave,
                'nombre' => 'Ind A1',
                'ponderacion_indicador' => 50,
            ])
            ->assertRedirect();

        $this->assertSame($commitmentBId, (int) ComIndicator::query()->findOrFail($indicatorA1)->com_commitment_id);
        $this->assertSame(2, (int) ComIndicator::query()->findOrFail($indicatorA1)->orden);
        $this->assertSame(1, (int) ComIndicator::query()->findOrFail($indicatorB1)->orden);
    }

    /**
     * @return array{0: int, 1: array<int, int>}
     */
    private function seedIndicatorsStructure(): array
    {
        [$commitmentAId] = $this->seedCommitments();
        $suffix = uniqid();

        $id1 = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentAId,
            'clave' => 'I1-'.$suffix,
            'nombre' => 'Uno',
            'ponderacion_indicador' => 50,
            'descripcion' => null,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id2 = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentAId,
            'clave' => 'I2-'.$suffix,
            'nombre' => 'Dos',
            'ponderacion_indicador' => 50,
            'descripcion' => null,
            'orden' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$commitmentAId, [$id1, $id2]];
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int}
     */
    private function seedTwoCommitmentsWithIndicators(): array
    {
        [$commitmentAId, $commitmentBId] = $this->seedCommitments();
        $suffix = uniqid();

        $indicatorA1 = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentAId,
            'clave' => 'A1-'.$suffix,
            'nombre' => 'A1',
            'ponderacion_indicador' => 50,
            'descripcion' => null,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorA2 = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentAId,
            'clave' => 'A2-'.$suffix,
            'nombre' => 'A2',
            'ponderacion_indicador' => 50,
            'descripcion' => null,
            'orden' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorB1 = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentBId,
            'clave' => 'B1-'.$suffix,
            'nombre' => 'B1',
            'ponderacion_indicador' => 100,
            'descripcion' => null,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$commitmentAId, $commitmentBId, $indicatorA1, $indicatorA2, $indicatorB1];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function seedCommitments(): array
    {
        $suffix = uniqid();

        $commitmentAId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => 1,
            'clave' => 'CA-'.$suffix,
            'nombre' => 'Compromiso A',
            'descripcion' => null,
            'orden' => 1,
            'year_id' => $this->year->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commitmentBId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => 1,
            'clave' => 'CB-'.$suffix,
            'nombre' => 'Compromiso B',
            'descripcion' => null,
            'orden' => 2,
            'year_id' => $this->year->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$commitmentAId, $commitmentBId];
    }

    private function grantIndicadorBreadPermissions(Role $role, bool $edit = true): void
    {
        $actions = ['browse', 'read'];
        if ($edit) {
            $actions = array_merge($actions, ['edit', 'add', 'delete']);
        }

        foreach ($actions as $action) {
            $permissionId = Permission::firstOrCreate(
                ['key' => $action.'_com_indicators'],
                ['table_name' => 'com_indicators']
            )->id;
            $role->permissions()->syncWithoutDetaching([$permissionId]);
        }
    }

    private function seedIndicadoresBread(): void
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

        DataRow::unguarded(function () use ($dataType): void {
            foreach ([
                ['field' => 'com_commitment_id', 'type' => 'read_only', 'order' => 2],
                ['field' => 'clave', 'type' => 'text', 'order' => 3],
                ['field' => 'nombre', 'type' => 'text', 'order' => 4],
                ['field' => 'ponderacion_indicador', 'type' => 'number', 'order' => 5],
            ] as $row) {
                DataRow::query()->updateOrCreate(
                    [
                        'data_type_id' => $dataType->id,
                        'field' => $row['field'],
                    ],
                    [
                        'type' => $row['type'],
                        'display_name' => $row['field'],
                        'required' => $row['field'] === 'com_commitment_id' ? 1 : 0,
                        'browse' => $row['field'] === 'com_commitment_id' ? 0 : 1,
                        'read' => 1,
                        'edit' => 1,
                        'add' => 1,
                        'delete' => 0,
                        'details' => '{}',
                        'order' => $row['order'],
                    ]
                );
            }
        });
    }

    private function seedDependency(): int
    {
        return (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-STRUCT',
            'nombre' => 'Servicio estructura',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
