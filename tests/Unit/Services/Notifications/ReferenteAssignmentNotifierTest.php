<?php

namespace Tests\Unit\Services\Notifications;

use App\Mail\ReferenteAssignedMail;
use App\Models\CenHieDependency;
use App\Models\ComIndicatorSetting;
use App\Models\IndicatorType;
use App\Models\MetIndicator;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Notifications\ReferenteAssignmentMailData;
use App\Services\Notifications\ReferenteAssignmentMailDataFactory;
use App\Services\Notifications\ReferenteAssignmentNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReferenteAssignmentNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_new_referentes_queues_mail_for_valid_email(): void
    {
        Mail::fake();

        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'email' => 'referente@example.com',
        ]);
        $referente->role()->associate($referenteRole)->save();

        $mailData = new ReferenteAssignmentMailData(
            moduleLabel: 'Metas',
            indicatorClave: 'MET-01',
            indicatorNombre: 'Indicador prueba',
            contextLabel: 'Metas 18834',
            servicioSaludNombre: 'SS Test',
            ano: 2026,
            showUrl: 'https://example.test/ficha',
            cargaUrl: 'https://example.test/carga',
        );

        $queued = app(ReferenteAssignmentNotifier::class)->notifyNewReferentes([$referente->id], $mailData);

        $this->assertTrue($queued);
        Mail::assertQueued(ReferenteAssignedMail::class, function (ReferenteAssignedMail $mail) use ($referente): bool {
            return $mail->hasTo($referente->email)
                && $mail->mailData->indicatorClave === 'MET-01';
        });
    }

    public function test_notify_skips_invalid_email_without_failing_batch(): void
    {
        Mail::fake();

        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $invalid = User::factory()->create([
            'role_id' => $referenteRole->id,
            'email' => 'no-es-email',
        ]);
        $valid = User::factory()->create([
            'role_id' => $referenteRole->id,
            'email' => 'valido@example.com',
        ]);

        $mailData = new ReferenteAssignmentMailData(
            moduleLabel: 'COMGES',
            indicatorClave: 'IND-01',
            indicatorNombre: 'Indicador COMGES',
            contextLabel: 'Compromiso',
            servicioSaludNombre: 'SS Test',
            ano: 2026,
            showUrl: 'https://example.test/comges',
        );

        $queued = app(ReferenteAssignmentNotifier::class)->notifyNewReferentes(
            [$invalid->id, $valid->id],
            $mailData,
        );

        $this->assertTrue($queued);
        Mail::assertQueued(ReferenteAssignedMail::class, 1);
    }

    public function test_diff_new_ids_returns_only_added_users(): void
    {
        $previous = collect([1, 2]);
        $current = collect([2, 3, 4]);

        $diff = ReferenteAssignmentNotifier::diffNewIds($previous, $current);

        $this->assertSame([3, 4], $diff->all());
    }

    public function test_diff_new_ids_ignores_duplicate_pivot_ids(): void
    {
        $previous = collect([1, 1, 1, 2, 2]);
        $current = collect([1, 1, 2, 2, 2, 3, 3]);

        $diff = ReferenteAssignmentNotifier::diffNewIds($previous, $current);

        $this->assertSame([3], $diff->all());
    }

    public function test_notify_new_referentes_dedupes_repeated_user_ids(): void
    {
        Mail::fake();

        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'email' => 'dedupe@example.com',
        ]);

        $mailData = new ReferenteAssignmentMailData(
            moduleLabel: 'COMGES',
            indicatorClave: 'IND-DUP',
            indicatorNombre: 'Indicador',
            contextLabel: 'Compromiso',
            servicioSaludNombre: 'SS Test',
            ano: 2026,
            showUrl: 'https://example.test/comges',
        );

        $queued = app(ReferenteAssignmentNotifier::class)->notifyNewReferentes(
            [$referente->id, $referente->id, $referente->id],
            $mailData,
        );

        $this->assertTrue($queued);
        Mail::assertQueued(ReferenteAssignedMail::class, 1);
    }

    public function test_factory_builds_metas_mail_data_with_routes(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio Metas',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $instrument = $this->indicatorTypeByRoute();
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $instrument->id,
            'clave' => 'M-01',
            'nombre' => 'Indicador Metas',
            'decimales' => 2,
            'year_id' => $year->id,
            'orden' => 1,
            'estado' => true,
        ]));
        $dependency = CenHieDependency::query()->findOrFail(1);

        $mailData = ReferenteAssignmentMailDataFactory::forMetasIndicator(
            $indicator,
            $instrument,
            $dependency,
            $year,
        );

        $this->assertSame('Metas', $mailData->moduleLabel);
        $this->assertSame('M-01', $mailData->indicatorClave);
        $this->assertStringContainsString('metas-18834', $mailData->showUrl);
        $this->assertNotNull($mailData->cargaUrl);
    }

    public function test_factory_builds_comges_mail_data(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

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

        $depId = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-COM',
            'nombre' => 'Servicio COMGES',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commitmentId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => 1,
            'clave' => 'C-01',
            'nombre' => 'Compromiso prueba',
            'descripcion' => null,
            'orden' => 1,
            'year_id' => $year->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentId,
            'clave' => 'IND-C',
            'nombre' => 'Indicador COMGES',
            'ponderacion_indicador' => 100,
            'descripcion' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $setting = ComIndicatorSetting::unguarded(fn () => ComIndicatorSetting::create([
            'cen_hie_dependency_id' => $depId,
            'com_indicator_id' => $indicatorId,
        ]));

        $mailData = ReferenteAssignmentMailDataFactory::forComgesSetting($setting, $year);

        $this->assertSame('COMGES', $mailData->moduleLabel);
        $this->assertSame('IND-C', $mailData->indicatorClave);
        $this->assertSame('Compromiso prueba', $mailData->contextLabel);
        $this->assertStringContainsString((string) $indicatorId, $mailData->showUrl);
        $this->assertNull($mailData->cargaUrl);
    }
}
