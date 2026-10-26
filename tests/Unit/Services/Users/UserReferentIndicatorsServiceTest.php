<?php

namespace Tests\Unit\Services\Users;

use App\Models\CenHieDependency;
use App\Models\ComIndicatorSetting;
use App\Models\IndicatorType;
use App\Models\MetIndicator;
use App\Models\MetIndicatorReferente;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Users\UserReferentIndicatorsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserReferentIndicatorsServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserReferentIndicatorsService $service;

    private Year $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(UserReferentIndicatorsService::class);
        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));
    }

    public function test_returns_metas_and_comges_rows_for_referente(): void
    {
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $user = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => 1,
        ]);

        $indicatorType = IndicatorType::unguarded(fn () => IndicatorType::create([
            'clave_indicador' => 'metas18834',
            'nombre_indicador' => 'Metas Ley 18.834',
            'route' => 'metas-18834',
            'estado' => true,
            'orden' => 1,
        ]));

        $metIndicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $indicatorType->id,
            'clave' => 'M-01',
            'nombre' => 'Indicador Metas',
            'decimales' => 2,
            'year_id' => $this->year->id,
            'orden' => 1,
            'estado' => true,
        ]));

        MetIndicatorReferente::unguarded(fn () => MetIndicatorReferente::create([
            'met_indicator_id' => $metIndicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $user->id,
        ]));

        $commitmentId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => 1,
            'clave' => 'C-01',
            'nombre' => 'Compromiso Gestión',
            'descripcion' => null,
            'orden' => 1,
            'year_id' => $this->year->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $comIndicatorId = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentId,
            'clave' => 'CG-01',
            'nombre' => 'Indicador COMGES',
            'ponderacion_indicador' => 100,
            'descripcion' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $setting = ComIndicatorSetting::unguarded(fn () => ComIndicatorSetting::create([
            'cen_hie_dependency_id' => 1,
            'com_indicator_id' => $comIndicatorId,
        ]));
        $setting->users()->attach($user->id);

        $rows = $this->service->forUser($user, $this->year);

        $this->assertCount(2, $rows);

        $metasRow = $rows->firstWhere('module', 'Metas');
        $this->assertNotNull($metasRow);
        $this->assertSame('Metas Ley 18.834', $metasRow['instrument']);
        $this->assertSame('M-01', $metasRow['clave']);
        $this->assertSame('Indicador Metas', $metasRow['nombre']);
        $this->assertNotNull($metasRow['url']);

        $comgesRow = $rows->firstWhere('module', 'COMGES');
        $this->assertNotNull($comgesRow);
        $this->assertSame('Compromiso Gestión', $comgesRow['instrument']);
        $this->assertSame('CG-01', $comgesRow['clave']);
        $this->assertSame('Indicador COMGES', $comgesRow['nombre']);
        $this->assertNotNull($comgesRow['url']);
    }

    public function test_ignores_assignments_from_other_ss_or_year(): void
    {
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $user = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => 1,
        ]);

        $otherYear = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 0]));

        $indicatorType = IndicatorType::unguarded(fn () => IndicatorType::create([
            'clave_indicador' => 'metas18834',
            'nombre_indicador' => 'Metas Ley 18.834',
            'route' => 'metas-18834',
            'estado' => true,
            'orden' => 1,
        ]));

        $metIndicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $indicatorType->id,
            'clave' => 'M-OTRO',
            'nombre' => 'Otro año',
            'decimales' => 2,
            'year_id' => $otherYear->id,
            'orden' => 1,
            'estado' => true,
        ]));

        MetIndicatorReferente::unguarded(fn () => MetIndicatorReferente::create([
            'met_indicator_id' => $metIndicator->id,
            'year_id' => $otherYear->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $user->id,
        ]));

        $this->assertTrue($this->service->forUser($user, $this->year)->isEmpty());
    }
}
