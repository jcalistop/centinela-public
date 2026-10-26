<?php

declare(strict_types=1);

namespace Tests\Feature\Ux;

use App\Models\CenHieDependency;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Year;
use App\Support\Voyager\BrowseSoporteVoyagerMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
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
            ->assertSee('https://ssreloncavi.redsalud.gob.cl/', false);

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
            ->assertSee('https://ssreloncavi.redsalud.gob.cl/', false);
    }

    public function test_login_page_does_not_show_support_legend(): void
    {
        $this->get(route('voyager.login'))
            ->assertOk()
            ->assertDontSee('¿Dudas o problemas? Utiliza el módulo Soporte.', false);
    }
}
