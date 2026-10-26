<?php

namespace Tests\Feature\Metas;

use App\Mail\MetasPerfBaselineReportMail;
use App\Models\CenHieDependency;
use App\Models\IndicatorTypeSetting;
use App\Models\Role;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MetasPerfBaselineCommandTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => true]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->admin->role()->associate($adminRole)->save();

        $type = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::firstOrCreate(
            ['cen_hie_dependency_id' => 1, 'module_key' => 'metas-18834'],
            ['enabled' => true],
        ));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::firstOrCreate(
            [
                'indicator_type_id' => $type->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => 1,
            ],
            [
                'etapa_vigente' => 'AUTOEVALUACION',
                'habilitado' => true,
            ],
        ));
    }

    public function test_rejects_missing_user(): void
    {
        $this->artisan('centinela:metas:perf-baseline', [
            '--user_id' => 999999,
            '--no-mail' => true,
            '--runs' => 1,
            '--scenarios' => 'catalog',
        ])->assertFailed();
    }

    public function test_rejects_unknown_scenario(): void
    {
        $this->artisan('centinela:metas:perf-baseline', [
            '--user_id' => $this->admin->id,
            '--year' => 2026,
            '--no-mail' => true,
            '--runs' => 1,
            '--scenarios' => 'no-existe',
        ])->assertFailed();
    }

    public function test_writes_report_and_skips_mail_with_flag(): void
    {
        Mail::fake();

        $outputDir = storage_path('app/reports/metas-perf-baseline-test');
        if (is_dir($outputDir)) {
            foreach (glob($outputDir.DIRECTORY_SEPARATOR.'*') ?: [] as $old) {
                @unlink($old);
            }
        }

        $this->artisan('centinela:metas:perf-baseline', [
            '--user_id' => $this->admin->id,
            '--year' => 2026,
            '--cen_hie_dependency_id' => 1,
            '--no-mail' => true,
            '--runs' => 1,
            '--scenarios' => 'catalog,hub,results_summary',
            '--output' => 'reports/metas-perf-baseline-test',
        ])->assertSuccessful();

        $this->assertDirectoryExists($outputDir);
        $files = glob($outputDir.DIRECTORY_SEPARATOR.'*.md') ?: [];
        $this->assertCount(1, $files, 'Debe existir exactamente un informe Markdown nuevo');
        $this->assertMatchesRegularExpression(
            '/^metas-perf-baseline_\d{4}-\d{2}-\d{2}_\d{4}\.md$/',
            basename($files[0]),
            'El nombre del informe debe incluir prefijo y marca temporal'
        );
        $jsonFiles = glob($outputDir.DIRECTORY_SEPARATOR.'*.json') ?: [];
        $this->assertCount(1, $jsonFiles);

        $markdown = file_get_contents($files[0]);
        $this->assertIsString($markdown);
        $this->assertStringContainsString('Hallazgos', $markdown);
        $this->assertStringContainsString('Leyenda de métricas', $markdown);
        $this->assertStringContainsString('Catálogo de indicadores', $markdown);
        $this->assertStringContainsString('catalog', $markdown);

        Mail::assertNothingQueued();
    }

    public function test_queues_mail_when_alert_email_configured(): void
    {
        Mail::fake();
        Config::set('centinela.scheduler.alert_email', 'ops@example.test');

        $this->artisan('centinela:metas:perf-baseline', [
            '--user_id' => $this->admin->id,
            '--year' => 2026,
            '--runs' => 1,
            '--scenarios' => 'catalog',
            '--output' => 'reports/metas-perf-baseline-mail',
        ])->assertSuccessful();

        Mail::assertQueued(MetasPerfBaselineReportMail::class);
    }

    public function test_warns_without_recipient_and_succeeds(): void
    {
        Mail::fake();
        Config::set('centinela.scheduler.alert_email', '');

        $this->artisan('centinela:metas:perf-baseline', [
            '--user_id' => $this->admin->id,
            '--year' => 2026,
            '--runs' => 1,
            '--scenarios' => 'hub',
            '--output' => 'reports/metas-perf-baseline-empty-mail',
        ])->assertSuccessful();

        Mail::assertNothingQueued();
    }
}
