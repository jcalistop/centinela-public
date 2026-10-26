<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @deprecated SDD-023 Módulo invitado deprecado (v4.2.0). La limpieza de rol/usuario en BD
 *             queda en `2026_05_29_100000_remove_guest_invitado_role_and_user.php`.
 *             Se mantiene para compatibilidad con despliegues que aún no ejecutaron la limpieza.
 *
 * Bootstrap SDD-023: rol `invitado`, usuario técnico cuyo email toma
 * `config('centinela.guest_login.user_email')` (env `CENTINELA_GUEST_USER_EMAIL` vía `config/centinela.php`),
 * y permisos copiados desde el rol `visualizador` cuando existe (paridad en despliegue).
 *
 * Operaciones que ajusten permisos solo en Voyager pueden divergir hasta el próximo `migrate:fresh`.
 * Rollback: elimina el usuario con ese email y `user_roles` asociados; solo borra el rol
 * `invitado` y sus `permission_role` si no queda ningún usuario con ese `role_id`.
 */
return new class extends Migration
{
    private function guestUserEmail(): string
    {
        $email = trim((string) config('centinela.guest_login.user_email', 'invitado@centinela.local'));

        return $email !== '' ? $email : 'invitado@centinela.local';
    }

    public function up(): void
    {
        $guestEmail = $this->guestUserEmail();
        $now = now();

        $invitadoRoleId = DB::table('roles')->where('name', 'invitado')->value('id');
        if (! $invitadoRoleId) {
            $invitadoRoleId = DB::table('roles')->insertGetId([
                'name' => 'invitado',
                'display_name' => 'Invitado',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $invitadoRoleId = (int) $invitadoRoleId;

        $visualizadorRoleId = DB::table('roles')->where('name', 'visualizador')->value('id');
        if ($visualizadorRoleId) {
            $permissionIds = DB::table('permission_role')
                ->where('role_id', (int) $visualizadorRoleId)
                ->pluck('permission_id');

            $rows = $permissionIds->map(fn ($pid): array => [
                'permission_id' => (int) $pid,
                'role_id' => $invitadoRoleId,
            ])->all();

            if ($rows !== []) {
                DB::table('permission_role')->insertOrIgnore($rows);
            }
        }

        $hasAnyPermission = DB::table('permission_role')
            ->where('role_id', $invitadoRoleId)
            ->exists();

        if (! $hasAnyPermission) {
            $browseAdminId = DB::table('permissions')->where('key', 'browse_admin')->value('id');
            if ($browseAdminId) {
                DB::table('permission_role')->insertOrIgnore([
                    [
                        'permission_id' => (int) $browseAdminId,
                        'role_id' => $invitadoRoleId,
                    ],
                ]);
            }
        }

        $cenHieDependencyId = (int) (DB::table('cen_hie_dependencies')->orderBy('id')->value('id') ?? 1);

        $existingUserId = DB::table('users')->where('email', $guestEmail)->value('id');
        if (! $existingUserId) {
            $userId = DB::table('users')->insertGetId([
                'role_id' => $invitadoRoleId,
                'name' => 'Usuario invitado',
                'email' => $guestEmail,
                'password' => Hash::make(Str::random(64)),
                'cen_hie_dependency_id' => $cenHieDependencyId,
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $userId = (int) $existingUserId;
            DB::table('users')->where('id', $userId)->update([
                'role_id' => $invitadoRoleId,
                'updated_at' => $now,
            ]);
        }

        DB::table('user_roles')->insertOrIgnore([
            'user_id' => $userId,
            'role_id' => $invitadoRoleId,
        ]);
    }

    public function down(): void
    {
        $userId = DB::table('users')->where('email', $this->guestUserEmail())->value('id');
        if ($userId) {
            DB::table('user_roles')->where('user_id', (int) $userId)->delete();
            DB::table('users')->where('id', (int) $userId)->delete();
        }

        $invitadoRoleId = DB::table('roles')->where('name', 'invitado')->value('id');
        if (! $invitadoRoleId) {
            return;
        }
        $invitadoRoleId = (int) $invitadoRoleId;

        if (DB::table('users')->where('role_id', $invitadoRoleId)->exists()) {
            return;
        }

        DB::table('permission_role')->where('role_id', $invitadoRoleId)->delete();
        DB::table('roles')->where('id', $invitadoRoleId)->delete();
    }
};
