<?php

namespace Tests\Unit\Models;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleNotAdminScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_not_admin_scope_excludes_only_administrador(): void
    {
        // Migrate puede haber dejado `agente` (SDD-248); el scope se prueba con un set controlado.
        Role::query()->delete();

        Role::query()->insert([
            ['id' => 1, 'name' => 'admin', 'display_name' => 'Administrador'],
            ['id' => 2, 'name' => 'control-gestion', 'display_name' => 'Control de Gestión'],
            ['id' => 5, 'name' => 'parametrizador', 'display_name' => 'Parametrizador'],
        ]);

        $names = Role::query()->notAdmin()->orderBy('id')->pluck('name')->all();

        $this->assertSame(['control-gestion', 'parametrizador'], $names);
        $this->assertNotContains('admin', $names);
    }
}
