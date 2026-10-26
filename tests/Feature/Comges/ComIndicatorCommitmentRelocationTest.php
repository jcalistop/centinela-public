<?php

namespace Tests\Feature\Comges;

use App\Http\Controllers\Voyager\ComIndicatorController;
use App\Models\ComIndicator;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use TCG\Voyager\Models\DataRow;
use TCG\Voyager\Models\DataType;
use TCG\Voyager\Models\Permission;
use Tests\Support\CreatesMinimalDocumentsBread;
use Tests\TestCase;

class ComIndicatorCommitmentRelocationTest extends TestCase
{
    use CreatesMinimalDocumentsBread;
    use RefreshDatabase;

    private Year $year;

    private User $admin;

    private User $parametrizador;

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

        $paramRole = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);
        $this->parametrizador = User::factory()->create([
            'role_id' => $paramRole->id,
            'cen_hie_dependency_id' => $dependencyId,
        ]);
        $this->parametrizador->role()->associate($paramRole)->save();

        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $this->referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => $dependencyId,
        ]);
        $this->referente->role()->associate($referenteRole)->save();

        foreach ([$adminRole, $paramRole] as $role) {
            $this->grantBrowseAdminPermissionToRole($role);
            $this->grantIndicadorBreadPermissions($role, edit: true);
        }

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

    public function test_admin_edit_shows_commitment_select(): void
    {
        [$indicatorId] = $this->seedIndicatorWithCommitments();

        $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin/indicadores-comges/'.$indicatorId.'/edit')
            ->assertOk()
            ->assertSee('name="com_commitment_id"', false)
            ->assertSee('<select', false);
    }

    public function test_user_without_edit_permission_cannot_access_edit_form(): void
    {
        [$indicatorId] = $this->seedIndicatorWithCommitments();

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin/indicadores-comges/'.$indicatorId.'/edit')
            ->assertForbidden();
    }

    public function test_admin_can_relocate_indicator_to_another_commitment_same_year(): void
    {
        [$indicatorId, , $commitmentBId] = $this->seedIndicatorWithCommitments();

        $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $this->year])
            ->put('/admin/indicadores-comges/'.$indicatorId, $this->updatePayload($indicatorId, $commitmentBId))
            ->assertRedirect();

        $this->assertSame($commitmentBId, (int) ComIndicator::query()->findOrFail($indicatorId)->com_commitment_id);
    }

    public function test_parametrizador_can_relocate_indicator_to_another_commitment_same_year(): void
    {
        [$indicatorId, , $commitmentBId] = $this->seedIndicatorWithCommitments();

        $this->actingAs($this->parametrizador)
            ->withSession(['indicator_year' => $this->year])
            ->put('/admin/indicadores-comges/'.$indicatorId, $this->updatePayload($indicatorId, $commitmentBId))
            ->assertRedirect();

        $this->assertSame($commitmentBId, (int) ComIndicator::query()->findOrFail($indicatorId)->com_commitment_id);
    }

    public function test_user_without_edit_permission_cannot_relocate_via_post(): void
    {
        [$indicatorId, $commitmentA, $commitmentBId] = $this->seedIndicatorWithCommitments();

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->put('/admin/indicadores-comges/'.$indicatorId, $this->updatePayload($indicatorId, $commitmentBId))
            ->assertForbidden();

        $this->assertSame((int) $commitmentA['id'], (int) ComIndicator::query()->findOrFail($indicatorId)->com_commitment_id);
    }

    public function test_admin_cannot_relocate_to_commitment_from_another_year(): void
    {
        [$indicatorId, , , $otherYearCommitmentId] = $this->seedIndicatorWithCommitments(withOtherYearCommitment: true);
        $this->assertIsInt($otherYearCommitmentId);

        $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $this->year])
            ->from('/admin/indicadores-comges/'.$indicatorId.'/edit')
            ->put('/admin/indicadores-comges/'.$indicatorId, $this->updatePayload($indicatorId, $otherYearCommitmentId))
            ->assertSessionHasErrors('com_commitment_id');
    }

    public function test_admin_cannot_relocate_with_empty_commitment_id(): void
    {
        [$indicatorId] = $this->seedIndicatorWithCommitments();

        $payload = $this->updatePayload($indicatorId, 1);
        $payload['com_commitment_id'] = '';

        $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $this->year])
            ->from('/admin/indicadores-comges/'.$indicatorId.'/edit')
            ->put('/admin/indicadores-comges/'.$indicatorId, $payload)
            ->assertSessionHasErrors('com_commitment_id');
    }

    public function test_create_still_uses_readonly_commitment_from_query(): void
    {
        [, $commitmentA] = $this->seedIndicatorWithCommitments();

        $response = $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin/indicadores-comges/create?com_commitment_id='.$commitmentA['id']);

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringNotContainsString('<select name="com_commitment_id"', $content);
        $this->assertStringContainsString('value="'.$commitmentA['id'].'"', $content);
    }

    /**
     * @return array{0: int, 1: array{id: int, clave: string}, 2: int, 3: int|null}
     */
    private function seedIndicatorWithCommitments(bool $withOtherYearCommitment = false): array
    {
        $suffix = bin2hex(random_bytes(4));

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

        $indicatorId = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentAId,
            'clave' => 'IND-'.$suffix,
            'nombre' => 'Indicador',
            'ponderacion_indicador' => 100,
            'descripcion' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherYearCommitmentId = null;
        if ($withOtherYearCommitment) {
            $otherYear = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 0]));
            $otherYearCommitmentId = (int) DB::table('com_commitments')->insertGetId([
                'indicator_type_id' => 1,
                'clave' => 'CO-'.$suffix,
                'nombre' => 'Compromiso otro año',
                'descripcion' => null,
                'orden' => 1,
                'year_id' => $otherYear->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            $indicatorId,
            ['id' => $commitmentAId, 'clave' => 'CA-'.$suffix],
            $commitmentBId,
            $otherYearCommitmentId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function updatePayload(int $indicatorId, int $commitmentId): array
    {
        $indicator = ComIndicator::query()->findOrFail($indicatorId);

        return [
            'com_commitment_id' => $commitmentId,
            'clave' => $indicator->clave,
            'nombre' => $indicator->nombre,
            'ponderacion_indicador' => $indicator->ponderacion_indicador,
        ];
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
            DataRow::query()->updateOrCreate(
                [
                    'data_type_id' => $dataType->id,
                    'field' => 'com_commitment_id',
                ],
                [
                    'type' => 'read_only',
                    'display_name' => 'Compromiso',
                    'required' => 1,
                    'browse' => 0,
                    'read' => 1,
                    'edit' => 1,
                    'add' => 1,
                    'delete' => 0,
                    'details' => json_encode([
                        'relationship' => 'comCommitment',
                        'field' => 'clave',
                        'view_edit' => 'voyager::indicadores-comges.formfields.commitment-select-edit',
                    ]),
                    'order' => 2,
                ]
            );

            foreach ([
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
                        'required' => 0,
                        'browse' => 1,
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
            'codigo' => 'SS-RELOC',
            'nombre' => 'Servicio reubicación',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
