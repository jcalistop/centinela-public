<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SDD-248 (enmienda): garantiza el rol Voyager `agente` en deploy vía `migrate`
 * (no depender solo de MinimalPlatformSeeder ni de `centinela:agent:issue-token`).
 *
 * Idempotente por `name`. Prefiere `id = 6` (canónico MinimalPlatform) si está libre;
 * si 6 ya está ocupado por otro rol, inserta sin forzar id.
 * Sin permisos BREAD: el rol es solo API/MCP (ability Sanctum `read`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $exists = DB::table('roles')->where('name', 'agente')->exists();
        if ($exists) {
            return;
        }

        $now = now();
        $row = [
            'name' => 'agente',
            'display_name' => 'Agente',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // Evita ocupar ids 1–5 en SQLite testing (autoincrement tras seed/delete de invitado).
        if (! DB::table('roles')->where('id', 6)->exists()) {
            $row['id'] = 6;
        }

        DB::table('roles')->insert($row);
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $roleId = DB::table('roles')->where('name', 'agente')->value('id');
        if ($roleId === null) {
            return;
        }

        $roleId = (int) $roleId;

        $usersWithRole = Schema::hasTable('users')
            && DB::table('users')->where('role_id', $roleId)->exists();

        $pivotUsers = Schema::hasTable('user_roles')
            && DB::table('user_roles')->where('role_id', $roleId)->exists();

        if ($usersWithRole || $pivotUsers) {
            return;
        }

        if (Schema::hasTable('permission_role')) {
            DB::table('permission_role')->where('role_id', $roleId)->delete();
        }

        DB::table('roles')->where('id', $roleId)->delete();
    }
};
