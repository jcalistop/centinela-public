<?php

namespace Tests\Unit\Services\Alerts;

use App\Models\ComCommitment;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Alerts\CentinelaVisualAlertService;
use App\Services\Alerts\Providers\ComgesDashboardListProvider;
use App\Support\Alerts\AlertContext;
use App\Support\Alerts\AlertSurface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CentinelaDashboardSummaryAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_provider_dedupes_identical_alerts(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $hieId = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-'.$suffix,
            'nombre' => 'SS',
            'tipo_dependencia' => 'SERVICIO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $yearId = (int) DB::table('years')->insertGetId([
            'ano' => 2025,
            'default' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $year = Year::findOrFail($yearId);
        $role = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Param']);
        $user = User::factory()->create(['role_id' => $role->id, 'cen_hie_dependency_id' => $hieId]);
        $user->role()->associate($role)->save();

        $data = $this->seedTwoCutoffsWrongPonderacion($year->id, $hieId);
        $commitment = ComCommitment::query()
            ->with([
                'comIndicators.comIndicatorCutoffs.cutoff',
                'comIndicators.comIndicatorCutoffs.comActions.comSensitivityTables.comSensitivityDependencies',
            ])
            ->findOrFail($data['commitment_id']);

        $service = new CentinelaVisualAlertService;
        $service->register(new ComgesDashboardListProvider);

        $context = new AlertContext(
            user: $user,
            year: $year,
            surface: AlertSurface::Dashboard,
            usesScoreScale: true,
            cenHieDependencyId: $hieId,
        );

        $collected = $service->collectForModule($context, 'comges', collect([$commitment]));
        $payload = $service->toDashboardListPayload($collected);

        $ponderacionAlerts = array_filter(
            $payload,
            fn (array $row): bool => str_contains($row['title'], 'ponderación')
        );

        $this->assertCount(2, $ponderacionAlerts);

        foreach ($ponderacionAlerts as $alert) {
            $this->assertStringContainsString('/admin/indicadores-comges/'.$data['indicator_id'], $alert['url']);
            $this->assertStringNotContainsString('/compromisos/', $alert['url']);
            $this->assertStringNotContainsString('/edit', $alert['url']);
            $this->assertStringContainsString('Ind', $alert['body']);
            $this->assertStringContainsString('Compromiso único', $alert['body']);
        }
    }

    public function test_control_gestion_sensitivity_alert_opens_indicator_show(): void
    {
        $fixture = $this->seedSensitivityPendingFixture();
        $payload = $this->collectDashboardPayload($fixture, $fixture['control_gestion'], usesScoreScale: true);

        $alerts = $this->alertsWithTitle($payload, 'COMGES: sensibilidad pendiente');
        $this->assertCount(1, $alerts);
        $this->assertStringContainsString('Indicador Sens', $alerts[0]['body']);
        $this->assertStringContainsString('Compromiso Sens', $alerts[0]['body']);
        $this->assertStringContainsString('Corte I', $alerts[0]['body']);
        $this->assertStringContainsString('/admin/indicadores-comges/'.$fixture['indicator_id'], $alerts[0]['url']);
        $this->assertStringNotContainsString('/compromisos/', $alerts[0]['url']);
        $this->assertStringNotContainsString('/edit', $alerts[0]['url']);
    }

    public function test_two_indicators_on_same_commitment_do_not_collapse_ponderacion_alerts(): void
    {
        $fixture = $this->seedTwoIndicatorsWrongPonderacion();
        $payload = $this->collectDashboardPayload($fixture, $fixture['parametrizador'], usesScoreScale: true);

        $alerts = $this->alertsWithTitle($payload, 'COMGES: ponderación de acciones');
        $this->assertCount(2, $alerts);

        $urls = array_column($alerts, 'url');
        $this->assertCount(2, array_unique($urls));
        $this->assertStringContainsString('/admin/indicadores-comges/'.$fixture['indicator_ids'][0], $urls[0].$urls[1]);
        $this->assertStringContainsString('/admin/indicadores-comges/'.$fixture['indicator_ids'][1], $urls[0].$urls[1]);

        $bodies = implode("\n", array_column($alerts, 'body'));
        $this->assertStringContainsString('Indicador A', $bodies);
        $this->assertStringContainsString('Indicador B', $bodies);
        $this->assertStringContainsString('Compromiso Duo', $bodies);
    }

    public function test_sensitivity_and_ponderacion_urls_never_point_to_compromisos_edit(): void
    {
        $sens = $this->seedSensitivityPendingFixture();
        $pond = $this->seedTwoIndicatorsWrongPonderacion();

        $cgPayload = $this->collectDashboardPayload($sens, $sens['control_gestion'], usesScoreScale: true);
        $paramPayload = $this->collectDashboardPayload($pond, $pond['parametrizador'], usesScoreScale: true);

        foreach (array_merge($cgPayload, $paramPayload) as $alert) {
            if (! str_contains($alert['title'], 'sensibilidad') && ! str_contains($alert['title'], 'ponderación')) {
                continue;
            }

            $this->assertStringNotContainsString('/compromisos/', $alert['url']);
            $this->assertDoesNotMatchRegularExpression('#/edit($|\?)#', $alert['url']);
            $this->assertStringContainsString('/admin/indicadores-comges/', $alert['url']);
        }
    }

    public function test_apelacion_cutoff_does_not_emit_ponderacion_alert(): void
    {
        $fixture = $this->seedApelacionWrongPonderacion();
        $payload = $this->collectDashboardPayload($fixture, $fixture['parametrizador'], usesScoreScale: true);

        $this->assertSame([], $this->alertsWithTitle($payload, 'COMGES: ponderación de acciones'));
    }

    public function test_control_gestion_sees_pending_verification_alert(): void
    {
        $fixture = $this->seedPendingVerificationFixture();
        $payload = $this->collectDashboardPayload($fixture, $fixture['control_gestion'], usesScoreScale: true);

        $this->assertPendingVerificationAlert($payload, $fixture['indicator_id']);
    }

    public function test_assigned_referente_sees_pending_verification_alert(): void
    {
        $fixture = $this->seedPendingVerificationFixture();
        $payload = $this->collectDashboardPayload($fixture, $fixture['referente'], usesScoreScale: true);

        $this->assertPendingVerificationAlert($payload, $fixture['indicator_id']);
    }

    public function test_unassigned_referente_does_not_see_pending_verification_alert(): void
    {
        $fixture = $this->seedPendingVerificationFixture();
        $payload = $this->collectDashboardPayload($fixture, $fixture['other_referente'], usesScoreScale: true);

        $this->assertSame([], $this->pendingVerificationAlerts($payload));
    }

    public function test_referente_with_extra_control_gestion_role_still_requires_assignment(): void
    {
        $fixture = $this->seedPendingVerificationFixture();
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'CG']);
        $fixture['other_referente']->roles()->syncWithoutDetaching([$cgRole->id]);
        $user = $fixture['other_referente']->fresh(['role', 'roles']);

        $this->assertSame('referente', $user->role?->name);
        $this->assertTrue($user->hasRole('control-gestion'));

        $payload = $this->collectDashboardPayload($fixture, $user, usesScoreScale: true);

        $this->assertSame([], $this->pendingVerificationAlerts($payload));
    }

    public function test_visualizador_does_not_see_pending_verification_alert(): void
    {
        $fixture = $this->seedPendingVerificationFixture();
        $payload = $this->collectDashboardPayload($fixture, $fixture['visualizador'], usesScoreScale: true);

        $this->assertSame([], $this->pendingVerificationAlerts($payload));
    }

    public function test_year_without_score_scale_does_not_emit_pending_verification_alert(): void
    {
        $fixture = $this->seedPendingVerificationFixture();
        $payload = $this->collectDashboardPayload($fixture, $fixture['control_gestion'], usesScoreScale: false);

        $this->assertSame([], $this->pendingVerificationAlerts($payload));
    }

    public function test_loaded_files_hide_pending_verification_alert(): void
    {
        $fixture = $this->seedPendingVerificationFixture(withFiles: true);
        $payload = $this->collectDashboardPayload($fixture, $fixture['control_gestion'], usesScoreScale: true);

        $this->assertSame([], $this->pendingVerificationAlerts($payload));
    }

    public function test_cutoff_without_actions_does_not_invent_pending_verification_alert(): void
    {
        $fixture = $this->seedPendingVerificationFixture(withDocument: false, withAction: false);
        $payload = $this->collectDashboardPayload($fixture, $fixture['control_gestion'], usesScoreScale: true);

        $this->assertSame([], $this->pendingVerificationAlerts($payload));
    }

    public function test_null_hie_id_does_not_emit_pending_verification_alert(): void
    {
        $fixture = $this->seedPendingVerificationFixture();
        $commitment = $this->loadCommitmentForDashboard($fixture['commitment_id']);
        $payload = $this->toPayload(
            $fixture['year'],
            $fixture['control_gestion'],
            $commitment,
            usesScoreScale: true,
            hieId: null,
        );

        $this->assertSame([], $this->pendingVerificationAlerts($payload));
    }

    /**
     * @param  array{year: Year, commitment_id: int}  $fixture
     * @return list<array{severity: string, title: string, body: string, url: string}>
     */
    private function collectDashboardPayload(array $fixture, User $user, bool $usesScoreScale): array
    {
        $commitment = $this->loadCommitmentForDashboard($fixture['commitment_id']);

        return $this->toPayload($fixture['year'], $user, $commitment, $usesScoreScale, $user->cen_hie_dependency_id);
    }

    /**
     * @return list<array{severity: string, title: string, body: string, url: string}>
     */
    private function toPayload(
        Year $year,
        User $user,
        ComCommitment $commitment,
        bool $usesScoreScale,
        ?int $hieId,
    ): array {
        $service = new CentinelaVisualAlertService;
        $service->register(new ComgesDashboardListProvider);

        $context = new AlertContext(
            user: $user,
            year: $year,
            surface: AlertSurface::Dashboard,
            usesScoreScale: $usesScoreScale,
            cenHieDependencyId: $hieId,
        );

        return $service->toDashboardListPayload(
            $service->collectForModule($context, 'comges', collect([$commitment]))
        );
    }

    private function loadCommitmentForDashboard(int $commitmentId): ComCommitment
    {
        return ComCommitment::query()
            ->with([
                'comIndicators.comIndicatorCutoffs.cutoff',
                'comIndicators.comIndicatorCutoffs.comActions.comVerifiableDocuments',
                'comIndicators.comIndicatorCutoffs.comActions.comSensitivityTables.comSensitivityDependencies',
            ])
            ->findOrFail($commitmentId);
    }

    /**
     * @param  list<array{severity: string, title: string, body: string, url: string}>  $payload
     * @return list<array{severity: string, title: string, body: string, url: string}>
     */
    private function alertsWithTitle(array $payload, string $title): array
    {
        return array_values(array_filter(
            $payload,
            fn (array $row): bool => $row['title'] === $title
        ));
    }

    /**
     * @param  list<array{severity: string, title: string, body: string, url: string}>  $payload
     * @return list<array{severity: string, title: string, body: string, url: string}>
     */
    private function pendingVerificationAlerts(array $payload): array
    {
        return $this->alertsWithTitle($payload, 'Pendiente medios de verificación');
    }

    /**
     * @param  list<array{severity: string, title: string, body: string, url: string}>  $payload
     */
    private function assertPendingVerificationAlert(array $payload, int $indicatorId): void
    {
        $alerts = $this->pendingVerificationAlerts($payload);
        $this->assertCount(1, $alerts);
        $this->assertStringContainsString('Indicador MV', $alerts[0]['body']);
        $this->assertStringContainsString('Compromiso MV', $alerts[0]['body']);
        $this->assertStringContainsString('Corte I', $alerts[0]['body']);
        $this->assertStringContainsString('/admin/indicadores-comges/'.$indicatorId, $alerts[0]['url']);
    }

    /**
     * @return array{
     *     year: Year,
     *     commitment_id: int,
     *     indicator_id: int,
     *     control_gestion: User,
     *     referente: User,
     *     other_referente: User,
     *     visualizador: User
     * }
     */
    private function seedPendingVerificationFixture(
        bool $withFiles = false,
        bool $withDocument = true,
        bool $withAction = true,
    ): array {
        $this->registerDashboardStubRoutes();

        $suffix = bin2hex(random_bytes(3));
        $hieId = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-MV-'.$suffix,
            'nombre' => 'SS MV',
            'tipo_dependencia' => 'SERVICIO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $yearId = (int) DB::table('years')->insertGetId([
            'ano' => 2025,
            'default' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $year = Year::findOrFail($yearId);

        $controlGestion = $this->makeUserWithRole('control-gestion', $hieId);
        $referente = $this->makeUserWithRole('referente', $hieId);
        $otherReferente = $this->makeUserWithRole('referente', $hieId);
        $visualizador = $this->makeUserWithRole('visualizador', $hieId);

        $indicatorTypeId = (int) DB::table('indicator_types')->insertGetId([
            'clave_indicador' => 'cg-mv-'.$suffix,
            'nombre_indicador' => 'COMGES',
            'route' => 'comp',
            'estado' => 1,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commitmentId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => $indicatorTypeId,
            'clave' => 'C-MV',
            'nombre' => 'Compromiso MV',
            'year_id' => $yearId,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentId,
            'clave' => 'I-MV',
            'nombre' => 'Indicador MV',
            'ponderacion_indicador' => 100,
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

        $linkId = (int) DB::table('com_indicator_cutoffs')->insertGetId([
            'com_indicator_id' => $indicatorId,
            'cutoff_id' => $cutoffId,
            'ponderacion_corte' => 100,
            'evaluacion_local' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($withAction) {
            $actionId = (int) DB::table('com_actions')->insertGetId([
                'com_indicator_cutoff_id' => $linkId,
                'numero' => '1',
                'ponderacion_accion' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($withDocument) {
                $archivos = $withFiles
                    ? json_encode([['download_link' => '2025/mv.pdf', 'original_name' => 'mv.pdf']])
                    : '[]';

                DB::table('com_verifiable_documents')->insert([
                    'com_action_id' => $actionId,
                    'cen_hie_dependency_id' => $hieId,
                    'archivos' => $archivos,
                    'descripcion' => 'MV',
                    'verifiable_type' => 'EVALUACION',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $settingId = (int) DB::table('com_indicator_settings')->insertGetId([
            'cen_hie_dependency_id' => $hieId,
            'com_indicator_id' => $indicatorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('com_indicator_setting_user')->insert([
            'com_indicator_setting_id' => $settingId,
            'user_id' => $referente->id,
        ]);

        return [
            'year' => $year,
            'commitment_id' => $commitmentId,
            'indicator_id' => $indicatorId,
            'control_gestion' => $controlGestion,
            'referente' => $referente,
            'other_referente' => $otherReferente,
            'visualizador' => $visualizador,
        ];
    }

    private function makeUserWithRole(string $roleName, int $hieId): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => $roleName]);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => $hieId,
        ]);
        $user->role()->associate($role)->save();

        return $user->fresh(['role']);
    }

    private function registerDashboardStubRoutes(): void
    {
        if (! Route::has('voyager.indicadores-comges.show')) {
            Route::get('/admin/indicadores-comges/{id}', fn () => '')->name('voyager.indicadores-comges.show');
        }
    }

    /**
     * @return array{year: Year, commitment_id: int, indicator_id: int, control_gestion: User}
     */
    private function seedSensitivityPendingFixture(): array
    {
        $this->registerDashboardStubRoutes();

        $suffix = bin2hex(random_bytes(3));
        $hieId = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-SENS-'.$suffix,
            'nombre' => 'SS Sens',
            'tipo_dependencia' => 'SERVICIO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $yearId = (int) DB::table('years')->insertGetId([
            'ano' => 2025,
            'default' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $year = Year::findOrFail($yearId);
        $controlGestion = $this->makeUserWithRole('control-gestion', $hieId);

        $indicatorTypeId = (int) DB::table('indicator_types')->insertGetId([
            'clave_indicador' => 'cg-sens-'.$suffix,
            'nombre_indicador' => 'COMGES Sens '.$suffix,
            'route' => 'comp-sens-'.$suffix,
            'estado' => 1,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commitmentId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => $indicatorTypeId,
            'clave' => 'C-SENS',
            'nombre' => 'Compromiso Sens',
            'year_id' => $yearId,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentId,
            'clave' => 'I-SENS',
            'nombre' => 'Indicador Sens',
            'ponderacion_indicador' => 100,
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

        $linkId = (int) DB::table('com_indicator_cutoffs')->insertGetId([
            'com_indicator_id' => $indicatorId,
            'cutoff_id' => $cutoffId,
            'ponderacion_corte' => 100,
            'evaluacion_local' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $actionId = (int) DB::table('com_actions')->insertGetId([
            'com_indicator_cutoff_id' => $linkId,
            'numero' => '1',
            'ponderacion_accion' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('com_sensitivity_tables')->insert([
            'com_action_id' => $actionId,
            'meta_numero' => 90,
            'valores' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'year' => $year,
            'commitment_id' => $commitmentId,
            'indicator_id' => $indicatorId,
            'control_gestion' => $controlGestion,
        ];
    }

    /**
     * @return array{year: Year, commitment_id: int, indicator_ids: list<int>, parametrizador: User}
     */
    private function seedTwoIndicatorsWrongPonderacion(): array
    {
        $this->registerDashboardStubRoutes();

        $suffix = bin2hex(random_bytes(3));
        $hieId = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-DUO-'.$suffix,
            'nombre' => 'SS Duo',
            'tipo_dependencia' => 'SERVICIO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $yearId = (int) DB::table('years')->insertGetId([
            'ano' => 2025,
            'default' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $year = Year::findOrFail($yearId);
        $parametrizador = $this->makeUserWithRole('parametrizador', $hieId);

        $indicatorTypeId = (int) DB::table('indicator_types')->insertGetId([
            'clave_indicador' => 'cg-duo-'.$suffix,
            'nombre_indicador' => 'COMGES Duo '.$suffix,
            'route' => 'comp-duo-'.$suffix,
            'estado' => 1,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commitmentId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => $indicatorTypeId,
            'clave' => 'C-DUO',
            'nombre' => 'Compromiso Duo',
            'year_id' => $yearId,
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

        $indicatorIds = [];
        foreach (['Indicador A', 'Indicador B'] as $i => $nombre) {
            $indicatorId = (int) DB::table('com_indicators')->insertGetId([
                'com_commitment_id' => $commitmentId,
                'clave' => 'I-DUO-'.$i,
                'nombre' => $nombre,
                'ponderacion_indicador' => 50,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $indicatorIds[] = $indicatorId;

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
                'ponderacion_accion' => 50,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'year' => $year,
            'commitment_id' => $commitmentId,
            'indicator_ids' => $indicatorIds,
            'parametrizador' => $parametrizador,
        ];
    }

    /**
     * @return array{year: Year, commitment_id: int, parametrizador: User}
     */
    private function seedApelacionWrongPonderacion(): array
    {
        $this->registerDashboardStubRoutes();

        $suffix = bin2hex(random_bytes(3));
        $hieId = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-APEL-'.$suffix,
            'nombre' => 'SS Apel',
            'tipo_dependencia' => 'SERVICIO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $yearId = (int) DB::table('years')->insertGetId([
            'ano' => 2025,
            'default' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $year = Year::findOrFail($yearId);
        $parametrizador = $this->makeUserWithRole('parametrizador', $hieId);

        $indicatorTypeId = (int) DB::table('indicator_types')->insertGetId([
            'clave_indicador' => 'cg-apel-'.$suffix,
            'nombre_indicador' => 'COMGES Apel '.$suffix,
            'route' => 'comp-apel-'.$suffix,
            'estado' => 1,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commitmentId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => $indicatorTypeId,
            'clave' => 'C-APEL',
            'nombre' => 'Compromiso Apel',
            'year_id' => $yearId,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentId,
            'clave' => 'I-APEL',
            'nombre' => 'Indicador Apel',
            'ponderacion_indicador' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cutoffId = (int) DB::table('cutoffs')->insertGetId([
            'clave' => 'APELACION',
            'tipo_corte' => 'TRIMESTRAL',
            'meses' => json_encode([1]),
            'nombre_corte' => 'Apelación',
            'orden' => 9,
            'habilitado' => 1,
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
            'ponderacion_accion' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'year' => $year,
            'commitment_id' => $commitmentId,
            'parametrizador' => $parametrizador,
        ];
    }

    /**
     * @return array{commitment_id: int, indicator_id: int}
     */
    private function seedTwoCutoffsWrongPonderacion(int $yearId, int $hieId): array
    {
        $indicatorTypeId = (int) DB::table('indicator_types')->insertGetId([
            'clave_indicador' => 'cg',
            'nombre_indicador' => 'COMGES',
            'route' => 'comp',
            'estado' => 1,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commitmentId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => $indicatorTypeId,
            'clave' => 'C1',
            'nombre' => 'Compromiso único',
            'year_id' => $yearId,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentId,
            'clave' => 'I1',
            'nombre' => 'Ind',
            'ponderacion_indicador' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['CORTE I', 'CORTE II'] as $i => $clave) {
            $cutoffId = (int) DB::table('cutoffs')->insertGetId([
                'clave' => $clave,
                'tipo_corte' => 'TRIMESTRAL',
                'meses' => json_encode([1]),
                'nombre_corte' => $clave,
                'orden' => $i + 1,
                'habilitado' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $linkId = (int) DB::table('com_indicator_cutoffs')->insertGetId([
                'com_indicator_id' => $indicatorId,
                'cutoff_id' => $cutoffId,
                'ponderacion_corte' => 50,
                'evaluacion_local' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('com_actions')->insert([
                'com_indicator_cutoff_id' => $linkId,
                'numero' => '1',
                'ponderacion_accion' => 50,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return ['commitment_id' => $commitmentId, 'indicator_id' => $indicatorId];
    }
}
