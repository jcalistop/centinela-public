<?php

namespace Tests\Feature\FileAccess;

use App\Http\Middleware\ReadOnlyFilePolicy;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ReadOnlyFilePolicyTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $roleName, string $displayName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => $displayName]);

        return User::unguarded(fn () => User::create([
            'name' => 'Test User',
            'email' => $roleName.'@example.test',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]));
    }

    /** @test */
    public function visualizador_puede_ver_imagen_de_settings_pero_no_otro_tipo_de_archivo(): void
    {
        $user = $this->makeUserWithRole('visualizador', 'Visualizador');

        Storage::fake('private');
        Storage::disk('private')->put('settings/logo.png', 'png-bytes');
        Storage::disk('private')->put('settings/secret.pdf', '%PDF-1.4');

        $this->actingAs($user)
            ->get('/storage/settings/logo.png')
            ->assertOk();

        $this->actingAs($user)
            ->get('/storage/settings/secret.pdf')
            ->assertForbidden();
    }

    /** @test */
    public function visualizador_no_puede_ver_avatares_privados(): void
    {
        $user = $this->makeUserWithRole('visualizador', 'Visualizador');

        Storage::fake('private');
        Storage::disk('private')->put('users/alguien.png', 'png-bytes');
        Storage::disk('private')->put('users/default.png', 'png-bytes');

        $this->actingAs($user)
            ->get('/storage/users/default.png')
            ->assertOk();

        $this->actingAs($user)
            ->get('/storage/users/alguien.png')
            ->assertForbidden();
    }

    /** @test */
    public function visualizador_no_puede_descargar_archivos_genericos_si_no_son_documentos(): void
    {
        $user = $this->makeUserWithRole('visualizador', 'Visualizador');

        // El middleware debe cortar antes de llegar al controlador (no requiere DataTypes de Voyager).
        $this->actingAs($user)
            ->get('/download/medios-verificacion/archivo/1/0')
            ->assertForbidden();
    }

    /** @test */
    public function visualizador_no_puede_ver_inline_medios_de_verificacion(): void
    {
        $user = $this->makeUserWithRole('visualizador', 'Visualizador');

        $this->actingAs($user)
            ->get('/view/medios-verificacion/archivo/1/0')
            ->assertForbidden();
    }

    /** @test */
    public function admin_no_es_bloqueado_por_readonly_al_intentar_vista_inline_de_medios_de_verificacion(): void
    {
        $user = $this->makeUserWithRole('admin', 'Administrador');

        $response = $this->actingAs($user)
            ->get('/view/medios-verificacion/archivo/1/0');

        $this->assertNotSame(403, $response->getStatusCode());
    }

    /** @test */
    public function visualizador_no_puede_ejecutar_operaciones_de_archivo_de_voyager(): void
    {
        $user = $this->makeUserWithRole('visualizador', 'Visualizador');

        $request = Request::create('/admin/media', 'GET');
        $request->setUserResolver(fn () => $user);

        $route = new Route(['GET'], '/admin/media', fn () => response('ok'));
        $route->name('voyager.media.index');
        $request->setRouteResolver(fn () => $route);

        $middleware = new ReadOnlyFilePolicy;

        try {
            $middleware->handle($request, fn () => response('ok'), 'voyager-file-ops');
            $this->fail('Se esperaba abort(403) para operaciones de archivo de Voyager.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
