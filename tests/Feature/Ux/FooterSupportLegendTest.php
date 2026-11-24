<?php

declare(strict_types=1);

namespace Tests\Feature\Ux;

use App\Models\CenHieDependency;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Year;
use App\Support\Configuration\ConfigurationService;
use App\Support\Voyager\BrowseSoporteVoyagerMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use TCG\Voyager\Models\Permission;
use TCG\Voyager\Models\Role;
use Tests\TestCase;

class FooterSupportLegendTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('centinela.support.enabled', true);

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

        BrowseSoporteVoyagerMenu::syncRoleGrants();
    }

    private function attachBrowseAdmin(Role $role): void
    {
        $perm = Permission::firstOrCreate(
            ['key' => 'browse_admin', 'table_name' => null],
            ['key' => 'browse_admin', 'table_name' => null]
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);
    }

    private function userWithRole(string $roleName, string $email): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);
        $this->attachBrowseAdmin($role);

        $user = User::unguarded(fn () => User::create([
            'name' => 'Footer '.$roleName,
            'email' => $email,
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]));
        $user->role()->associate($role)->save();

        return $user;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function persistInstanceSettings(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! DB::table('settings')->where('key', $key)->exists()) {
                DB::table('settings')->insert([
                    'key' => $key,
                    'display_name' => $key,
                    'value' => $value,
                    'details' => '',
                    'type' => 'text',
                    'order' => 1,
                    'group' => 'Servicio de Salud',
                ]);
            } else {
                DB::table('settings')->where('key', $key)->update(['value' => $value]);
            }
        }

        $configuration = app(ConfigurationService::class);
        $configuration->invalidate();
        $configuration->overrideConfig();
    }

    public function test_admin_footer_shows_legend_and_create_ticket_link(): void
    {
        $admin = $this->userWithRole('admin', 'admin-footer-soporte@test.local');
        $createUrl = route('centinela.support.create');

        $this->actingAs($admin)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin')
            ->assertOk()
            ->assertSee('¿Dudas o problemas? Utiliza el módulo Soporte.', false)
            ->assertSee('Crear ticket', false)
            ->assertSee($createUrl, false)
            ->assertSee('SS de prueba', false)
            ->assertDontSee('https://ssreloncavi.redsalud.gob.cl/', false)
            ->assertDontSee('Reloncaví', false);

        $this->actingAs($admin)
            ->withSession(['indicator_year' => $this->year])
            ->get($createUrl)
            ->assertOk();
    }

    public function test_parametrizador_footer_shows_legend_without_create_ticket_link(): void
    {
        $param = $this->userWithRole('parametrizador', 'param-footer-soporte@test.local');
        $createUrl = route('centinela.support.create');

        $this->assertFalse($param->can('create', SupportTicket::class));

        $this->actingAs($param)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin')
            ->assertOk()
            ->assertSee('¿Dudas o problemas? Utiliza el módulo Soporte.', false)
            ->assertDontSee('Crear ticket', false)
            ->assertDontSee($createUrl, false)
            ->assertSee('SS de prueba', false)
            ->assertDontSee('https://ssreloncavi.redsalud.gob.cl/', false);
    }

    public function test_footer_respects_instance_identity_settings(): void
    {
        $this->persistInstanceSettings([
            'instance.ss_display_name' => 'SS Laboratorio',
            'instance.ss_url' => 'https://ejemplo.gob.cl/',
            'instance.unit_name' => 'Unidad Demo',
        ]);

        $admin = $this->userWithRole('admin', 'admin-footer-instance@test.local');

        $this->actingAs($admin)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin')
            ->assertOk()
            ->assertSee('Unidad Demo', false)
            ->assertSee('SS Laboratorio', false)
            ->assertSee('https://ejemplo.gob.cl/', false);
    }

    public function test_footer_omits_link_when_instance_url_empty(): void
    {
        $this->persistInstanceSettings([
            'instance.ss_display_name' => 'SS Sin URL',
            'instance.ss_url' => '',
            'instance.unit_name' => 'Unidad Sin Link',
        ]);

        $admin = $this->userWithRole('admin', 'admin-footer-no-url@test.local');

        $html = $this->actingAs($admin)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin')
            ->assertOk()
            ->assertSee('SS Sin URL', false)
            ->assertSee('Unidad Sin Link', false)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/Unidad Sin Link,\s*SS Sin URL\./',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/Unidad Sin Link,[\s\S]*?<a\s+href=/',
            $html
        );
        $this->assertStringNotContainsString('Desarrollado por', $html);
    }

    public function test_login_page_does_not_show_support_legend(): void
    {
        $this->get(route('voyager.login'))
            ->assertOk()
            ->assertDontSee('¿Dudas o problemas? Utiliza el módulo Soporte.', false);
    }
}
