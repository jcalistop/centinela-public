<?php

namespace Tests\Unit\Services\Comges;

use App\Models\ComIndicator;
use App\Models\IndicatorAmbito;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Comges\ComgesCommitmentSearchService;
use App\Services\Indicators\IndicatorAmbitoAssignmentService;
use App\Support\Comges\ComgesBrowseContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ComgesCommitmentSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    private int $hieId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hieId = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-SVC',
            'nombre' => 'Servicio',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_search_prunes_non_matching_indicators_when_commitment_matched_via_child(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2024, 'default' => 1]));
        $this->seedTwoIndicators((int) $year->id);

        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'CG']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => $this->hieId,
        ]);
        $user->role()->associate($role)->save();

        $this->actingAs($user)->withSession(['indicator_year' => $year]);

        $context = ComgesBrowseContext::fromSession('VALECH');
        $commitment = app(ComgesCommitmentSearchService::class)->search($context)->first();

        $this->assertNotNull($commitment);
        $this->assertCount(1, $commitment->comIndicators);
        $this->assertSame('I-VALECH', $commitment->comIndicators->first()->clave);
    }

    public function test_search_filters_by_commitment_clave(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2024, 'default' => 1]));
        $this->seedGraph((int) $year->id, 'C-ONE', 'I-ONE');
        $this->seedGraph((int) $year->id, 'C-TWO', 'I-TWO');

        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'CG']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => $this->hieId,
        ]);
        $user->role()->associate($role)->save();

        $this->actingAs($user)->withSession(['indicator_year' => $year]);

        $context = ComgesBrowseContext::fromSession('C-ONE');
        $results = app(ComgesCommitmentSearchService::class)->search($context);

        $this->assertCount(1, $results);
        $this->assertSame('C-ONE', $results->first()->clave);
    }

    public function test_search_filters_indicators_by_ambito(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2024, 'default' => 1]));
        $this->seedTwoIndicators((int) $year->id);

        $ambito = IndicatorAmbito::query()->where('slug', 'ges')->firstOrFail();
        $tagged = ComIndicator::query()->where('clave', 'I-VALECH')->firstOrFail();
        app(IndicatorAmbitoAssignmentService::class)->sync($tagged, [$ambito->id]);

        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'CG']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => $this->hieId,
        ]);
        $user->role()->associate($role)->save();

        $this->actingAs($user)->withSession(['indicator_year' => $year]);

        $context = ComgesBrowseContext::fromSession('', (string) $ambito->id);
        $commitment = app(ComgesCommitmentSearchService::class)->search($context)->first();

        $this->assertNotNull($commitment);
        $this->assertCount(1, $commitment->comIndicators);
        $this->assertSame('I-VALECH', $commitment->comIndicators->first()->clave);
    }

    public function test_search_filters_indicators_without_ambito(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2024, 'default' => 1]));
        $this->seedTwoIndicators((int) $year->id);

        $ambito = IndicatorAmbito::query()->where('slug', 'ges')->firstOrFail();
        $tagged = ComIndicator::query()->where('clave', 'I-VALECH')->firstOrFail();
        app(IndicatorAmbitoAssignmentService::class)->sync($tagged, [$ambito->id]);

        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'CG']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => $this->hieId,
        ]);
        $user->role()->associate($role)->save();

        $this->actingAs($user)->withSession(['indicator_year' => $year]);

        $context = ComgesBrowseContext::fromSession('', 'none');
        $commitment = app(ComgesCommitmentSearchService::class)->search($context)->first();

        $this->assertNotNull($commitment);
        $this->assertCount(1, $commitment->comIndicators);
        $this->assertSame('I-OTHER', $commitment->comIndicators->first()->clave);
    }

    private function seedTwoIndicators(int $yearId): void
    {
        $indicatorTypeId = (int) DB::table('indicator_types')->insertGetId([
            'clave_indicador' => 'comges-mix',
            'nombre_indicador' => 'COMGES mix',
            'route' => 'compromisos',
            'estado' => 1,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cutoffId = (int) DB::table('cutoffs')->insertGetId([
            'clave' => 'CORTE I',
            'tipo_corte' => 'TRIMESTRAL',
            'meses' => json_encode([1]),
            'nombre_corte' => 'Corte I',
            'orden' => 1,
            'habilitado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commitmentId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => $indicatorTypeId,
            'clave' => 'C-MIX',
            'nombre' => 'Nombre',
            'year_id' => $yearId,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['I-VALECH', 'I-OTHER'] as $clave) {
            DB::table('com_indicators')->insert([
                'com_commitment_id' => $commitmentId,
                'clave' => $clave,
                'nombre' => 'Ind '.$clave,
                'ponderacion_indicador' => 50,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedGraph(int $yearId, string $commitmentClave, string $indicatorClave): void
    {
        $indicatorTypeId = (int) DB::table('indicator_types')->insertGetId([
            'clave_indicador' => 'comges-'.$commitmentClave,
            'nombre_indicador' => 'COMGES '.$commitmentClave,
            'route' => 'compromisos',
            'estado' => 1,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cutoffId = (int) DB::table('cutoffs')->insertGetId([
            'clave' => 'CORTE I',
            'tipo_corte' => 'TRIMESTRAL',
            'meses' => json_encode([1]),
            'nombre_corte' => 'Corte I',
            'orden' => 1,
            'habilitado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commitmentId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => $indicatorTypeId,
            'clave' => $commitmentClave,
            'nombre' => 'Nombre',
            'year_id' => $yearId,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentId,
            'clave' => $indicatorClave,
            'nombre' => 'Ind',
            'ponderacion_indicador' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $linkId = (int) DB::table('com_indicator_cutoffs')->insertGetId([
            'com_indicator_id' => $indicatorId,
            'cutoff_id' => $cutoffId,
            'ponderacion_corte' => 100,
            'evaluacion_local' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('com_actions')->insert([
            'com_indicator_cutoff_id' => $linkId,
            'numero' => '1',
            'ponderacion_accion' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
