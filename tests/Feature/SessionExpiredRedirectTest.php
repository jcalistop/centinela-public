<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureIndicatorYearIsSet;
use App\Models\CenHieDependency;
use App\Models\IndicatorTypeSetting;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Pruebas para el comportamiento del sistema cuando la sesión expira.
 *
 * Bug: cuando `indicator_year` no está en sesión (sesión expirada),
 * el middleware intentaba restaurar el año incluso para usuarios no autenticados,
 * fallando con 500/404 si no había Year en BD (ModelNotFoundException de firstOrFail).
 *
 * Comportamiento esperado: redirigir al login cuando la sesión se pierde
 * sin intentar consultar la base de datos.
 */
class SessionExpiredRedirectTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Cuando la sesión expira (sin auth ni indicator_year), el middleware
     * debe redirigir al login SIN intentar restaurar el año desde BD/cache.
     *
     * RED: Antes del fix, llamaba Year::getDefaultYear() en BD vacía
     * → ModelNotFoundException (404). No redirigía al login.
     *
     * @test
     */
    public function middleware_redirects_to_login_when_unauthenticated_and_no_indicator_year(): void
    {
        // BD vacía a propósito: ningún Year. Si el middleware llama Year::getDefaultYear(),
        // lanzará ModelNotFoundException en lugar de redirigir.
        $request = Request::create('/admin/test', 'GET');
        // No hay usuario autenticado (Auth::check() = false)

        $middleware = new EnsureIndicatorYearIsSet;
        $response = $middleware->handle($request, fn ($r) => response('ok', 200));

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('login', $response->getTargetUrl());
    }

    /**
     * El middleware tampoco debe lanzar excepción cuando la BD está vacía
     * y no hay usuario: simplemente redirigir al login.
     *
     * @test
     */
    public function middleware_does_not_throw_when_no_year_in_db_and_unauthenticated(): void
    {
        $request = Request::create('/admin/test', 'GET');

        $middleware = new EnsureIndicatorYearIsSet;

        // No debe lanzar ModelNotFoundException ni ninguna excepción
        $response = $middleware->handle($request, fn ($r) => response('ok', 200));

        // Solo puede ser redirect (no excepción)
        $this->assertContains($response->getStatusCode(), [301, 302, 303, 307, 308]);
    }

    /**
     * Cuando el usuario está autenticado pero indicator_year no está en sesión
     * (pérdida parcial de sesión), el middleware debe restaurar el año desde
     * caché y NO redirigir al login.
     *
     * @test
     */
    public function authenticated_user_with_missing_indicator_year_is_not_redirected_to_login(): void
    {
        Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $user = User::factory()->create(['role_id' => $adminRole->id, 'cen_hie_dependency_id' => 1]);
        $user->role()->associate($adminRole)->save();

        $indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $indicatorType->id,
            'year_id' => Year::query()->firstOrFail()->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => 1,
        ]));

        // Autenticado pero SIN indicator_year en sesión (simula pérdida parcial)
        $response = $this->actingAs($user)
            ->withSession([]) // sesión vacía, sin indicator_year
            ->get(route('metas.instrument.index', $indicatorType));

        // El middleware deja pasar (año restaurado desde caché).
        // No debe redirigir al login.
        $this->assertNotEquals(
            route('login'),
            $response->headers->get('Location'),
            'El middleware NO debe redirigir al login cuando el usuario está autenticado.'
        );
    }

    /**
     * El middleware no debe redirigir cuando el usuario está autenticado
     * y indicator_year ya está en sesión (flujo normal).
     *
     * @test
     */
    public function authenticated_user_with_indicator_year_in_session_is_not_redirected_to_login(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $user = User::factory()->create(['role_id' => $adminRole->id, 'cen_hie_dependency_id' => 1]);
        $user->role()->associate($adminRole)->save();

        $indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $indicatorType->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => 1,
        ]));

        $response = $this->actingAs($user)
            ->withSession(['indicator_year' => $year])
            ->get(route('metas.instrument.index', $indicatorType));

        // Flujo normal: no debe redirigir al login
        $this->assertNotEquals(
            route('login'),
            $response->headers->get('Location'),
            'El middleware NO debe redirigir al login en el flujo normal (session con indicator_year).'
        );
    }
}
